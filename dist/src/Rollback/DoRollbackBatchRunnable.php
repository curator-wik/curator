<?php


namespace Curator\Rollback;


use Curator\APIModel\v1\BatchRunnerRawProgressMessage;
use Curator\Batch\MessageCallbackRunnableInterface;
use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\Debug\Exception\ContextErrorException;

/**
 * Class DoRollbackBatchRunnable
 */
class DoRollbackBatchRunnable extends AbstractRunnable {

  /**
   * @var RollbackCaptureService $rollback_svc
   */
  protected $rollback_svc;

  public function __construct($id, RollbackCaptureService $rollback_svc) {
    parent::__construct($id);
    $this->rollback_svc = $rollback_svc;
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state)
  {
    /**
     * @var DoRollbackBatchTaskInstanceState $instance_state
     */
    $this->rollback_svc->fixupToCpkg($instance_state->getRollbackCapturePath());
  }
}
