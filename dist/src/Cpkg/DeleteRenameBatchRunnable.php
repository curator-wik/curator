<?php


namespace Curator\Cpkg;


use Curator\Batch\DescribedRunnableInterface;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\ChangeTypeDelete;
use Curator\Rollback\ChangeTypeRename;
use Curator\Rollback\RollbackCaptureService;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

class DeleteRenameBatchRunnable extends AbstractRunnable implements DescribedRunnableInterface {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var RollbackCaptureService $rollback
   */
  protected $rollback;

  /**
   * @var string $operation
   *   'rename' or 'delete'
   */
  protected $operation;

  /**
   * @var string $source
   *   FSAccessManager-recognized path to the file to operate on.
   */
  protected $source;

  /**
   * @var string|null $destination
   *   If a rename, the FSAccessManager-recognized new name of the file.
   */
  protected $destination;

  /**
   * DeleteRenameBatchRunnable constructor.
   * @param \Curator\FSAccess\FSAccessManager $fs_access
   * @param int $id
   *   The runnable id.
   * @param string $operation
   *   'delete' or 'rename'.
   * @param string $source
   *   FSAccessManager-recognized path to the file to operate on.
   * @param string|null $destination
   *   If a rename, the FSAccessManager-recognized new name of the file.
   */
  public function __construct(FSAccessManager $fs_access, RollbackCaptureService $rollback, $id, $operation, $source, $destination = NULL) {
    parent::__construct($id);
    $this->fs_access = $fs_access;
    $this->rollback = $rollback;
    $this->operation = $operation;
    $this->source = $source;
    $this->destination = $destination;
  }

  public function describe() {
    if ($this->operation == 'delete') {
      return sprintf('Deleting %s', $this->source);
    } else {
      return sprintf('Renaming %s to %s', $this->source, $this->destination);
    }
  }

  /**
   * @param TaskInterface $task
   * @param CpkgBatchTaskInstanceState $instance_state
   */
  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    if ($this->operation == 'delete') {
      if (empty($this->source)) {
        throw new \RuntimeException('No path provided to delete.');
      }
      $this->delete($this->source, $instance_state->getRollbackPath());
    } else if ($this->operation == 'rename') {
      if (empty($this->source)) {
        throw new \RuntimeException('No path provided to rename from.');
      }
      if (empty($this->destination)) {
        throw new \RuntimeException('No path provided to rename to.');
      }
      $this->rename($this->source, $this->destination, $instance_state->getRollbackPath());
    }
  }

  public function delete($path, $rollback_path) {
    $fs = $this->fs_access;
    if ($fs->isDir($path)) {
      $ls = $fs->ls($path);
      foreach ($ls as $child) {
        $this->delete($this->fs_access->ensureTerminatingSeparator($path) . $child);
      }
      $this->rollback->capture(new ChangeTypeDelete($path), $rollback_path, $this->getId());
      $fs->rmDir($path);
    } else {
      $this->rollback->capture(new ChangeTypeDelete($path), $rollback_path, $this->getId());
      try {
        $fs->unlink($path);
      } catch (FileNotFoundException $e) {}
    }
  }

  public function rename($from, $to, $rollback_path) {
    $fs = $this->fs_access;
    if ($fs->isDir($to)) {
      $this->delete($to);
    }

    $this->rollback->capture(new ChangeTypeRename($from, $to), $rollback_path, $this->getId());
    $fs->mv($from, $to);
  }

}
