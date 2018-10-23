<?php


namespace Curator\Rollback;


use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DoRollbackBatchTask
 * This and related batch classes really just wraps RollbackCaptureService::fixupToCpkg
 * in a single-runner batch task. RollbackCaptureService::fixupToCpkg is the first thing
 * that needs to happen when a rollback is required, and so this lets all of the rollback
 * processes be done in an in-order Batch TaskGroup.
 *
 * Service id: rollback.do_rollback_batch_task
 */
class DoRollbackBatchTask implements TaskInterface {

  /**
   * @var RollbackCaptureService $rollback_service
   */
  protected $rollback_service;

  public function __construct(RollbackCaptureService $rollback_service) {
    $this->rollback_service = $rollback_service;
  }

  public function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {}

  public function getRunnableIterator(TaskInstanceStateInterface $schedule, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    return new DoRollbackBatchRunnableIterator($this->rollback_service);
  }

  public function onRunnableError(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $exception, ProgressInfo $progress) { }

  public function assembleResultResponse($final_results) {
    return new Response();
  }

  public function supportsReduction() {
    return FALSE;
  }

  public function supportsUnaryPartialResult() {
    return FALSE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) { }

  public function updatePartialResult($new, $current = NULL) { }

}
