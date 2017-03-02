<?php

namespace Curator\Batch;

/**
 * Class TaskInstanceState
 */
class TaskInstanceState extends \mbaynton\BatchFramework\TaskInstanceState implements \Serializable {
  /**
   * @var string $taskServiceName
   */
  protected $taskServiceName;

  /**
   * TaskInstanceState constructor.
   *
   * @param string $task_service_name
   * @param int $task_id
   * @param int $num_runners
   * @param int $num_runnables_estimate
   */
  public function __construct($task_service_name, $task_id, $num_runners, $num_runnables_estimate) {
    $this->taskServiceName = $task_service_name;
    parent::__construct($task_id, $num_runners, $num_runnables_estimate);
  }

  public function getTaskServiceName() {
    return $this->taskServiceName;
  }

  /**
   * Performs the modify portion of the atomic read/modify/write.
   *
   * Precondition: Persistence exclusive lock is held by caller.
   *
   * @param \Curator\Batch\TaskInstanceState $read_state
   *   The deserialized instance as read from Persistence within the lock.
   */
  public function reconcileUpdateables(TaskInstanceState $read_state) {
    $this->num_runnables = $read_state->getNumRunnables() + $this->getUpdate_NumRunnables();
  }

  public function serialize() {
    $data = [
      'parent' => parent::serialize(),
      'taskServiceName' => $this->taskServiceName
    ];
    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    parent::unserialize($data['parent']);
    $this->taskServiceName = $data['taskServiceName'];
  }
}
