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

  public function getDecoderServiceName() {
    switch ($this->task) {
      case self::TASK_INIT_INTEGRATION_SECRET:
        return 'task.decoder.initialize_hmac_secret';
    }
    return NULL;
  }

  public function getRoute() {
    switch ($this->task) {
      case self::TASK_INIT_INTEGRATION_SECRET:
        return '/integration-utils/init-secret';
    }
  }
}
