<?php


namespace Curator\Cpkg;


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

  public function __construct(CpkgReader $reader) {
    $this->reader = $reader;
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
    // TODO: Implement onRunnableError() method.
    // Look at adding a RunnableErrorAggregatorInterface parameter, and persisting those via the BatchFramework?
  }

  public function assembleResultResponse($final_results) {
    return new Response();
  }

}
