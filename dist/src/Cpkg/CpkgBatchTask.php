<?php


namespace Curator\Cpkg;


use Curator\Batch\TaskGroupManager;
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
   * @var \Curator\Batch\TaskGroupManager $task_group_manager
   */
  protected $task_group_manager;

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

  public function __construct(CpkgReader $reader, TaskScheduler $scheduler, TaskGroupManager $taskGroupManager, RollbackCaptureService $rollback, RollbackCaptureNoOpService $null_rollback, RollbackInitiatorService $rollback_initiator) {
    $this->reader = $reader;
    $this->scheduler = $scheduler;
    $this->task_group_manager = $taskGroupManager;
    $this->rollback = $rollback;
    $this->null_rollback = $null_rollback;
    $this->rollback_initiator = $rollback_initiator;
  }

  /**
   * @return bool
   */
  public function supportsReduction() {
    return TRUE;
  }

  public function supportsUnaryPartialResult() {
    return TRUE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) {
    $r = $aggregator->getCollectedResults();
    return array_reduce($r, [$this, 'aggregateResults'], new CpkgResult());
  }

  public function updatePartialResult($new, $current = NULL) {
    if ($current === NULL) {
      return $new;
    } else {
      return $this->aggregateResults($new, $current);
    }
  }

  protected function aggregateResults(CpkgResult $a, CpkgResult $b) {
    $a->errorCount += $b->errorCount;
    if (empty($a->rollbackCaptureLocation) && !empty($b->rollbackCaptureLocation)) {
      $a->rollbackCaptureLocation = $b->rollbackCaptureLocation;
    }
    return $a;
  }

  public function onRunnableComplete(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    /** @var \Curator\Cpkg\CpkgResult $result */
    if ($result === NULL) {
      $result = new CpkgResult();
    }

    $result->rollbackCaptureLocation = $instance_state->getRollbackPath();
    $aggregator->collectResult($runnable, $result);
  }

  public function onRunnableError(TaskInstanceStateInterface $instance_state, RunnableInterface $runnable, $exception, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    $result = new CpkgResult();
    $result->errorCount = 1;
    $result->rollbackCaptureLocation = $instance_state->getRollbackPath();
    $aggregator->collectResult($runnable, $result);
  }

  public function assembleResultResponse($final_results) {
    /** @var \Curator\Cpkg\CpkgResult $final_results */
    if ($final_results !== null && $final_results->errorCount > 0) {
      // If there is a rollback capture path, initiate the rollback.
      if (!empty($final_results->rollbackCaptureLocation)) {
        // Prevent remaining Tasks in the update TaskGroup from running.
        $group = $this->scheduler->getCurrentGroupInSession();
        while($incomplete_task = $this->task_group_manager->getActiveTaskInstance($group)) {
          $this->task_group_manager->removeTaskInstance($group, $incomplete_task->getTaskId());
        }
        $this->scheduler->removeGroupFromSession($this->scheduler->getCurrentGroupInSession());

        // And schedule the rollback TaskGroup.
        $this->rollback_initiator->makeBatchTasks($final_results->rollbackCaptureLocation);
      }
    }

    return new Response();
  }

}
