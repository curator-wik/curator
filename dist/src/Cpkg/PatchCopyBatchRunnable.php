<?php


namespace Curator\Cpkg;


use Curator\Batch\DescribedRunnableInterface;
use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FSAccessManager;
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

  public function __construct(FSAccessManager $fs_access, ArchiveFileReader $reader, $id, $operation, $source_in_cpkg, $destination) {
    parent::__construct($id);

    $this->fs_access = $fs_access;
    $this->reader = $reader;
    $this->operation = $operation;
    $this->source_in_cpkg = $source_in_cpkg;
    $this->destination = $destination;
  }

  public function describe() {
    return sprintf('%sing %s', ucfirst($this->operation), $this->destination);
  }

  public function run(\mbaynton\BatchFramework\TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    if (empty($this->source_in_cpkg)) {
      throw new \RuntimeException('No path within cpkg provided.');
    }

    if (empty($this->destination)) {
      throw new \RuntimeException('No path provided to apply to.');
    }

    if ($this->operation == 'copy') {
      $this->copy();
    } else if ($this->operation == 'patch') {
      $this->patch();
    }
  }

  protected function copy() {
    if ($this->reader->isDir($this->source_in_cpkg)) {
      if ($this->fs_access->isFile($this->destination)) {
        $this->fs_access->unlink($this->destination);
      }

      if (! $this->fs_access->isDir($this->destination)) {
        $this->optimisticMkdir($this->destination);
      }
    } else if ($this->reader->isFile($this->source_in_cpkg)) {
      $containing_directory = $this->fs_access->ensureTerminatingSeparator($this->destination) . '..';
      if (! $this->fs_access->isDir(
        $containing_directory
      )) {
        $this->optimisticMkdir($containing_directory);
      }

      $this->fs_access->filePutContents($this->destination, $this->reader->getContent($this->source_in_cpkg));
    }
  }

  protected function patch() {
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

    $this->fs_access->filePutContents($this->destination, $patched_data);
  }

  protected function optimisticMkdir($path) {
    try {
      $this->fs_access->mkdir($path, TRUE);
    } catch (FileExistsException $e) {
      // A race exists among runners creating the directory structure -
      // a runner creating some descendant of $destination may run first,
      // and implicitly create this directory. We need to tolerate that.
      // FileExistsException code 0 indicates object already exists; it
      // cannot be anything but a directory object since we already deleted
      // any file at this path, assuming the cpkg is coherent.
      if ($e->getCode() != 0) {
        throw $e;
      }
    }
  }
}
