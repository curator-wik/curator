<?php


namespace Curator\Cpkg;


use Curator\Batch\TaskScheduler;
use Curator\Rollback\RollbackCaptureNoOpService;
use Curator\Rollback\RollbackCaptureService;
use Curator\Rollback\RollbackInitiatorService;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class CpkgBatchTask implements TaskInterface {
  /**
   * @var CpkgReader $reader
   */
  protected $reader;

  /**
   * @var TaskScheduler $scheduler
   */
  protected $scheduler;

  /**
   * @var RollbackCaptureService $rollback
   */
  protected $rollback;

  /**
   * @var RollbackCaptureNoOpService $null_rollback
   */
  protected $null_rollback;

  /**
   * @var RollbackInitiatorService $rollback_initiator
   */
  protected $rollback_initiator;

  public function __construct(CpkgReader $reader, TaskScheduler $scheduler, RollbackCaptureService $rollback, RollbackCaptureNoOpService $null_rollback, RollbackInitiatorService $rollback_initiator) {
    $this->reader = $reader;
    $this->scheduler = $scheduler;
    $this->rollback = $rollback;
    $this->null_rollback = $null_rollback;
    $this->rollback_initiator = $rollback_initiator;
  }

  /**
   * Reduction not needed: Runnable results are not gathered.
   *
   * @return bool
   */
  public function supportsReduction() {
    return FALSE;
  }

  public function supportsUnaryPartialResult() {
    return FALSE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) { }

  public function updatePartialResult($new, $current = NULL) { }

  public function onRunnableComplete(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) { }

  public function onRunnableError(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $exception, ProgressInfo $progress) {
    // When something goes wrong with replaying a cpkg, we want to stop making further changes from it.
    // The current TaskGroup is guaranteed to be the one that does those changes, so we unschedule it now.
    // It's possible, though, that another runner also encountered an error and already cancelled the group.
    // TODO: This assumes no subsequent task groups are scheduled after the cpkg update application one.
    //       Currently that's correct, but if there was ever a possibility of another one, we'd no longer be
    //       guaranteed that the current group is the one to cancel if it's non-null, and would need a more
    //       exact way to identify the task group type.
    $taskGroup = $this->scheduler->getCurrentGroupInSession();
    if ($taskGroup !== NULL) {
      $this->scheduler->removeGroupFromSession($taskGroup);
    }

    // If there is a rollback capture path, initiate the rollback.
    /** @var CpkgBatchTaskInstanceState $instance_state */
    if ($instance_state->getRollbackPath() !== '') {
      $this->rollback_initiator->makeBatchTasks($instance_state->getRollbackPath());
    }

    // TODO: explicit error message when rollback also fails. (very explicit? ;))

  }

  public function assembleResultResponse($final_results) {
    return new Response();
  }

}
