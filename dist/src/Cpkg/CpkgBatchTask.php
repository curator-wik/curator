<?php


namespace Curator\Cpkg;


use Curator\Batch\TaskScheduler;
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

  public function __construct(CpkgReader $reader, TaskScheduler $scheduler) {
    $this->reader = $reader;
    $this->scheduler = $scheduler;
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
    $this->scheduler->removeGroupFromSession($this->scheduler->getCurrentGroupInSession());
  }

  public function assembleResultResponse($final_results) {
    return new Response();
  }

}
