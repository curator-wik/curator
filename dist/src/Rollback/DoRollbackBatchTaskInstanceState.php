<?php


namespace Curator\Rollback;


use Curator\Batch\TaskInstanceState;

class DoRollbackBatchTaskInstanceState extends TaskInstanceState {
  protected $rollback_capture_path;

  public function __construct($task_id, $rollback_capture_path, $task_service_name) {
    $this->parentConstruct($task_service_name, $task_id, 1, 1);
    $this->rollback_capture_path = $rollback_capture_path;
  }

  protected function parentConstruct($task_service_name, $task_id, $num_runners, $num_runnables) {
    parent::__construct($task_service_name, $task_id, $num_runners, $num_runnables);
  }

  public function getRollbackCapturePath() {
    return $this->rollback_capture_path;
  }

  public function serialize() {
    $data = [
      'parent' => parent::serialize(),
      'rollback_capture_path' => $this->getRollbackCapturePath()
    ];

    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    parent::unserialize($data['parent']);

    $this->rollback_capture_path = $data['rollback_capture_path'];
  }
}
