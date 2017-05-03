<?php


namespace Curator\Task;

/**
 * Class ParameterlessTask
 *   A single integration script TaskInterface implementation for simple tasks
 *   that require no parameters or configuration.
 */
class ParameterlessTask implements TaskInterface {

  const TASK_INIT_INTEGRATION_SECRET = 1;

  /**
   * @var int $task
   */
  protected $task;

  /**
   * ParameterlessTask constructor.
   * @param int $task
   *   One of the ParameterlessTask::TASK_* constants.
   */
  public function __construct($task) {
    $this->task = $task;
  }

  public function getControllerName() {
    switch ($this->task) {
      case self::TASK_INIT_INTEGRATION_SECRET:
        return 'controller.integration.init_secret';
    }
  }
}
