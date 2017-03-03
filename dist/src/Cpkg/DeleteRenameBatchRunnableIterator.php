<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class DeleteRenameBatchRunnableIterator extends AbstractRunnableIterator {

  /**
   * @var \Curator\FSAccess\FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var int $start_index
   */
  protected $start_index;

  /**
   * @var int $current_index
   */
  protected $current_index;

  /**
   * @var int $increment
   */
  protected $increment;

  protected $deletes;

  protected $renames;

  public function __construct(FSAccessManager $fs_access, $deletes, $renames, $start_index, $increment) {
    $this->fs_access = $fs_access;
    $this->start_index = $start_index;
    $this->increment = $increment;
    $this->deletes = $deletes;
    $this->renames = $renames;

    $this->rewind();
  }

  protected function _seekRenames() {
    $n_delete_offset = count($this->deletes) % $this->increment;
    for($i = 1; $i <= $n_delete_offset; $i++) {
      next($this->renames);
    }

    $completed_renames_offset = $this->current_index - count($this->deletes);
    for ($i = 1; $i <= $completed_renames_offset; $i++) {
      next($this->renames);
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
    if ($this->current_index >= count($this->deletes)) {
      for ($i = 1; $i <= $this->increment; $i++) {
        next($this->renames);
      }
    }
    $this->current_index += $this->increment;
  }

  public function valid() {
    return $this->current_index < count($this->deletes) + count($this->renames);
  }

  public function rewind() {
    $this->current_index = $this->start_index;
    reset($this->renames);
    $this->_seekRenames();
  }
}
