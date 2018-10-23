<?php


namespace Curator\Cpkg;


use Curator\Batch\DescribedRunnableInterface;
use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\ChangeTypeDelete;
use Curator\Rollback\ChangeTypeMkdirTree;
use Curator\Rollback\ChangeTypePatch;
use Curator\Rollback\ChangeTypeWrite;
use Curator\Rollback\RollbackCaptureInterface;
use Curator\Rollback\RollbackCaptureService;
use DiffMatchPatch\DiffMatchPatch;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

/**
 * Class PatchCopyBatchRunnable
 *   Runnable for applying a patch or copying a full file.
 */
class PatchCopyBatchRunnable extends AbstractRunnable implements DescribedRunnableInterface {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var ArchiveFileReader $reader
   */
  protected $reader;

  /**
   * @var RollbackCaptureInterface $rollback
   */
  protected $rollback;

  /**
   * @var string $operation
   *   'patch' or 'copy'
   */
  protected $operation;

  /**
   * @var string $source_in_cpkg
   *   A path into the cpkg referencing the patch or full file to apply.
   */
  protected $source_in_cpkg;

  /**
   * @var string $destination
   *   FSAccessManager-recognized location of the new or patched file.
   */
  protected $destination;

  public function __construct(FSAccessManager $fs_access, ArchiveFileReader $reader, RollbackCaptureInterface $rollback, $id, $operation, $source_in_cpkg, $destination) {
    parent::__construct($id);

    $this->fs_access = $fs_access;
    $this->reader = $reader;
    $this->rollback = $rollback;
    $this->operation = $operation;
    $this->source_in_cpkg = $source_in_cpkg;
    $this->destination = $destination;
  }

  public function describe() {
    return sprintf('%sing %s', ucfirst($this->operation), $this->destination);
  }

  /**
   * @param \mbaynton\BatchFramework\TaskInterface $task
   * @param CpkgBatchTaskInstanceState $instance_state
   */
  public function run(\mbaynton\BatchFramework\TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    if (empty($this->source_in_cpkg)) {
      throw new \RuntimeException('No path within cpkg provided.');
    }

    if (empty($this->destination)) {
      throw new \RuntimeException('No path provided to apply to.');
    }

    if ($this->operation == 'copy') {
      $this->copy($instance_state->getRollbackPath());
    } else if ($this->operation == 'patch') {
      $this->patch($instance_state->getRollbackPath());
    }
  }

  protected function copy($rollback_path) {
    if ($this->reader->isDir($this->source_in_cpkg)) {
      if ($this->fs_access->isFile($this->destination)) {
        $this->rollback->capture(new ChangeTypeDelete($this->destination), $rollback_path, $this->getId());
        try {
          $this->fs_access->unlink($this->destination);
        } catch (FileNotFoundException $e) {
          // It's okay if the file we want to ensure isn't there already isn't there.
        }
      }

      if (! $this->fs_access->isDir($this->destination)) {
        $this->optimisticMkdir($this->destination, $rollback_path);
      }
    } else if ($this->reader->isFile($this->source_in_cpkg)) {
      $containing_directory = $this->fs_access->ensureTerminatingSeparator($this->destination) . '..';
      if (! $this->fs_access->isDir(
        $containing_directory
      )) {
        $this->optimisticMkdir($containing_directory, $rollback_path);
      }

      $this->rollback->capture(new ChangeTypeWrite($this->destination), $rollback_path, $this->getId());
      $this->fs_access->filePutContents($this->destination, $this->reader->getContent($this->source_in_cpkg));
    }
  }

  protected function patch($rollback_path) {
    // Sanity check.
    if (substr($this->source_in_cpkg, -6) != '.patch') {
      throw new \RuntimeException('Expected a .patch file, got ' . $this->source_in_cpkg);
    }

    $patch_target = $this->fs_access->fileGetContents($this->destination);

    // If checksum data is present, evaluate those.
    $meta_file = substr($this->source_in_cpkg, 0, -6) . '.meta';
    $meta_file = $this->reader->tryGetContent($meta_file);
    if ($meta_file) {
      $meta_file = json_decode($meta_file);
    }

    if ($meta_file && isset($meta_file->{'initial-md5'})) {
      if (md5($patch_target) != $meta_file->{'initial-md5'}) {
        // TODO: Add this to the result and report it somehow. Also skip resulting-md5.
      }
    }

    $patch = $this->reader->getContent($this->source_in_cpkg);

    // The fun part.
    $dmp = new DiffMatchPatch();
    $patch = $dmp->patch_fromText($patch);
    list($patched_data, $hunk_results) = $dmp->patch_apply($patch, $patch_target);
    $failed_hunks = array_filter($hunk_results, function($r) { return !$r;});

    if (count($failed_hunks)) {
      throw new \RuntimeException(
        sprintf('Patch could not be applied to your version of the file "%s". Failed at hunk(s) %s',
          $this->destination,
          implode(', ', array_map(function($i) { return $i + 1; }, array_keys($failed_hunks)))
        )
      );
    }

    $this->rollback->capture(new ChangeTypePatch($this->destination), $rollback_path, $this->getId());
    $this->fs_access->filePutContents($this->destination, $patched_data);
  }

  protected function optimisticMkdir($path, $rollback_path) {
    $required_dirs = NULL;
    try {
      $required_dirs = $this->fs_access->mkdir($path, TRUE, TRUE);
      $this->fs_access->mkdir($path, TRUE);
      $this->optimisticMkdirCaptureRollback($required_dirs, $rollback_path);
    } catch (FileExistsException $e) {
      // A race exists among runners creating the directory structure -
      // a runner creating some descendant of $destination may run first,
      // and implicitly create this directory. We need to tolerate that.
      // FileExistsException code 0 indicates object already exists; it
      // cannot be anything but a directory object since we already deleted
      // any file at this path, assuming the cpkg is coherent.
      if ($required_dirs !== NULL) {
        $this->optimisticMkdirCaptureRollback($required_dirs, $rollback_path);
      }
      if ($e->getCode() != 0) {
        throw $e;
      }
    }
  }

  protected function optimisticMkdirCaptureRollback($created_dirs, $rollback_path) {
    $this->rollback->capture(new ChangeTypeMkdirTree($created_dirs), $rollback_path, $this->getId());
  }
}
