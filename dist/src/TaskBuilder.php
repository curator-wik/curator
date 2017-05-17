<?php


namespace Curator;
use Curator\Task\ParameterlessTask;
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

  public function setIntegrationSecret($commit = FALSE) {
    $this->task = new ParameterlessTask(
      $commit ? ParameterlessTask::TASK_COMMIT_INTEGRATION_SECRET
        : ParameterlessTask::TASK_GEN_INTEGRATION_SECRET
    );
    return $this->task;
  }

  /**
   * @return \Curator\Task\TaskInterface
   */
  public function getTask() {
    return $this->task;
  }
}
