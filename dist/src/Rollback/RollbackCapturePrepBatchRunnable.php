<?php


namespace Curator\Rollback;


use Curator\Download\CpkgDownloadBatchTaskInstanceState;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

class RollbackCapturePrepBatchRunnable extends AbstractRunnable
{

  /**
   * @var RollbackCaptureService $rollback_service
   */
  protected $rollbackService;

  public function __construct(RollbackCaptureService $rollback_service, $id)
  {
    parent::__construct($id);
    $this->rollbackService = $rollback_service;
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    /** @var CpkgDownloadBatchTaskInstanceState $instance_state */
    $captureDir = $instance_state->getRollbackCaptureDir();
    $this->rollbackService->initializeCaptureDir($captureDir);
  }
}
