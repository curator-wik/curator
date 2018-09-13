<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\RollbackCaptureService;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class DeleteRenameBatchRunnableIterator extends AbstractRunnableIterator {

  /**
   * @var \Curator\FSAccess\FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var RollbackCaptureService $rollback
   */
  protected $rollback;

  /**
   * @var int $start_index
   */
  protected $start_index;

  /**
   * @var int $current_index
   */
  protected $current_index;

  protected $rename_seeked;

  /**
   * @var int $increment
   */
  protected $increment;

  protected $deletes;

  protected $renames;

  /**
   * DeleteRenameBatchRunnableIterator constructor.
   * @param \Curator\FSAccess\FSAccessManager $fs_access
   * @param $deletes
   * @param $renames
   * @param int $start_index
   *   0-based index into an imaginary concatenation of $deletes + $renames
   * @param $increment
   */
  public function __construct(FSAccessManager $fs_access, RollbackCaptureService $rollback, $deletes, $renames, $start_index, $increment) {
    $this->fs_access = $fs_access;
    $this->rollback = $rollback;
    $this->start_index = $start_index;
    $this->increment = $increment;
    $this->deletes = $deletes;
    $this->renames = $renames;

    $this->rewind();
  }

  protected function _seekRenames() {
    if ($this->current_index >= count($this->deletes) && ! $this->rename_seeked) {
      reset($this->renames);
      for ($i = 0; $i < $this->current_index - count($this->deletes); $i++) {
        next($this->renames);
      }
      $this->rename_seeked = TRUE;
    }
  }

  /**
   * @return DeleteRenameBatchRunnable
   */
  public function current() {
    if ($this->current_index < count($this->deletes)) {
      $operation = 'delete';
      $source = $this->deletes[$this->current_index];
      $destination = NULL;
    } else {
      $operation = 'rename';
      list($source, $destination) = each($this->renames);
    }

    return new DeleteRenameBatchRunnable(
      $this->fs_access,
      $this->current_index,
      $operation,
      $source,
      $destination
    );
  }

  /**
   * Moves the iterator forward to the next element.
   */
  public function next() {
    $this->current_index += $this->increment;
    if ($this->current_index >= count($this->deletes)) {
      if (! $this->rename_seeked) {
        $this->_seekRenames();
      } else {
        // One less than $this->increment because each() already incremented 1
        for ($i = 1; $i < $this->increment; $i++) {
          next($this->renames);
        }
      }
    }
  }

  public function valid() {
    return $this->current_index < count($this->deletes) + count($this->renames);
  }

  public function rewind() {
    $this->current_index = $this->start_index;
    $this->rename_seeked = FALSE;
    $this->_seekRenames();
  }
}
