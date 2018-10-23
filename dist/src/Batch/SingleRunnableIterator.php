<?php


namespace Curator\Batch;


use mbaynton\BatchFramework\AbstractRunnableIterator;
use mbaynton\BatchFramework\RunnableInterface;

/**
 * Class SingleRunnableIterator
 *
 * Implements an iterator useful for tasks that have a single runnable.
 *
 * To use, extend with a class that sets $this->runnable in its constructor.
 */
abstract class SingleRunnableIterator extends AbstractRunnableIterator
{
  /**
   * @var RunnableInterface $runnable
   */
  protected $runnable;

  /**
   * @var bool $valid
   */
  protected $is_valid;

  public function __construct()
  {
    $this->is_valid = TRUE;
  }

  public function current() {
    if ($this->valid()) {
      return $this->runnable;
    }
  }

  public function next() {
    $this->is_valid = FALSE;
  }

  public function valid() {
    return $this->is_valid;
  }

  public function rewind() {
    $this->is_valid = TRUE;
  }

}