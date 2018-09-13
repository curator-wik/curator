<?php

namespace Curator\Cpkg;


use Curator\Batch\TaskScheduler;
use mbaynton\BatchFramework\TaskInstanceState;

class CpkgBatchTaskInstanceState extends \Curator\Batch\TaskInstanceState  {
  protected $cpkg_path;

  protected $version;

  protected $rollback_path;

  public function __construct($service_name, $task_id, $num_runners, $num_runnables_estimate, $cpkg_path, $version, $rollback_path) {
    parent::__construct($service_name, $task_id, $num_runners, $num_runnables_estimate);
    $this->cpkg_path = $cpkg_path;
    $this->version = $version;
    $this->rollback_path = $rollback_path;
  }

  public function getCpkgPath() {
    return $this->cpkg_path;
  }

  public function getVersion() {
    return $this->version;
  }

  public function getRollbackPath() {
    return $this->rollback_path;
  }

  public function serialize() {
    $data = [
      'parent' => parent::serialize(),
      'cpkg_path' => $this->cpkg_path,
      'version' => $this->version,
      'rollback_path' => $this->rollback_path
    ];

    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    parent::unserialize($data['parent']);

    $this->cpkg_path = $data['cpkg_path'];
    $this->version = $data['version'];
    $this->rollback_path = $data['rollback_path'];
  }
}
