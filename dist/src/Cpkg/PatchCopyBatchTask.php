<?php


namespace Curator\Cpkg;


use Curator\Batch\TaskScheduler;
use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\RollbackCaptureNoOpService;
use Curator\Rollback\RollbackCaptureService;
use Curator\Rollback\RollbackInitiatorService;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

class PatchCopyBatchTask extends CpkgBatchTask {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  public function __construct(\Curator\Cpkg\CpkgReader $reader, FSAccessManager $fs_access, TaskScheduler $scheduler, RollbackCaptureService $rollback, RollbackCaptureNoOpService $null_rollback, RollbackInitiatorService $rollback_initiator) {
    parent::__construct($reader, $scheduler, $rollback, $null_rollback, $rollback_initiator);
    $this->fs_access = $fs_access;
  }

  public function getRunnerCount() {
    return 4;
  }

  public function getRunnableIterator(TaskInstanceStateInterface $instance_state, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    /**
     * @var CpkgBatchTaskInstanceState $instance_state
     */

    list ($start, $end) = self::getStartEndRunnableId($instance_state, $runner_rank);
    if ($last_processed_runnable_id !== NULL) {
      // If not on the first incarnation, always pick up where last left off.
      $start = $last_processed_runnable_id + 1;
    }

    // An empty rollback capture path is set to indicate that no rollback
    // should be captured, such as when actually applying previously captured
    // rollback cpkgs.
    $rollback_svc = $instance_state->getRollbackPath() === '' ?
      $this->null_rollback : $this->rollback;

    return new PatchCopyBatchRunnableIterator(
      $this->fs_access,
      $this->reader->getReaderPrimitives($instance_state->getCpkgPath()),
      $rollback_svc,
      $instance_state->getVersion(),
      $start,
      $end
    );
  }

  /**
   * @param TaskInstanceStateInterface $instance_state
   * @param int $runner_rank
   * @return int[]
   *   0: The id of the first runnable this runner should execute.
   *   1: The id of the last runnable this runner should execute.
   */
  public static function getStartEndRunnableId(TaskInstanceStateInterface $instance_state, $runner_rank) {
    /**
     * @var int $num_runnables_all_ranks
     *   The (minimum) number of runnables run by all runners.
     */
    $num_runnables_all_ranks = (int)($instance_state->getNumRunnables() / $instance_state->getNumRunners());
    /**
     * @var int $num_ranks_running_remainder
     *   The number of ranks that need to run one extra runnable.
     */
    $num_ranks_running_remainder = $instance_state->getNumRunnables() % $instance_state->getNumRunners();

    $first_rank_running_remainder = $instance_state->getNumRunners() - $num_ranks_running_remainder;
    if ($runner_rank >= $first_rank_running_remainder) {
      if ($num_runnables_all_ranks === 0) {
        $start = $end = $runner_rank - $first_rank_running_remainder;
      } else {
        if ($runner_rank > $first_rank_running_remainder) {
          $start = $runner_rank * ($num_runnables_all_ranks + 1);
        } else {
          $start = $runner_rank * $num_runnables_all_ranks;
        }
        $end = (($runner_rank + 1) * ($num_runnables_all_ranks + 1) - 1);
      }
    } else {
      $start = $runner_rank * $num_runnables_all_ranks;
      $end = (($runner_rank + 1) * $num_runnables_all_ranks) - 1;
    }

    return [$start, min($end, $instance_state->getNumRunnables() - 1)];
  }
}
