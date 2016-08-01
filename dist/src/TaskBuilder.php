<?php


namespace Curator;
use Curator\Task\TaskInterface;
use Curator\Task\UpdateTask;

/**
 * Class TaskBuilder
 *
 * @package Curator
 */
class TaskBuilder {

  /**
   * @var TaskInterface
   */
  protected $task;

  /**
   * Creates a new UpdateTask.
   *
   * @return UpdateTask
   */
  public function update($component = NULL) {
    $this->task = new UpdateTask($component);
    return $this->task;
  }

  /**
   * @return \Curator\Task\TaskInterface
   */
  public function getTask() {
    return $this->task;
  }
}
