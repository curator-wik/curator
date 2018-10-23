<?php


namespace Curator\Rollback;


use Curator\FSAccess\FSAccessInterface;
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
 * Class CleanupRollbackBatchTask
 * Simple single-runnable task that rm -rf's the rollback capture location, e.g. when
 * an update hsa completed.
 *
 * Service id: rollback.cleanup_rollback_batch_task
 */
class CleanupRollbackBatchTask implements TaskInterface {

  /**
   * @var FSAccessInterface $fs_access
   */
  protected $fs_access;

  public function __construct(FSAccessInterface $fs_access) {
    $this->fs_access = $fs_access;
  }

  public function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {}

  public function getRunnableIterator(TaskInstanceStateInterface $schedule, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    return new CleanupRollbackBatchRunnableIterator($this->fs_access);
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
