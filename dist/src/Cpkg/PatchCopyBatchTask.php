<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

class PatchCopyBatchTask extends CpkgBatchTask {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  public function __construct(\Curator\Cpkg\CpkgReader $reader, FSAccessManager $fs_access) {
    parent::__construct($reader);
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

    return new PatchCopyBatchRunnableIterator(
      $this->fs_access,
      new ArchiveFileReader($instance_state->getCpkgPath()),
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
  protected static function getStartEndRunnableId(TaskInstanceStateInterface $instance_state, $runner_rank) {
    /**
     * @var int $num_runnables_all_ranks
     *   The (minimum) number of runnables run by all runners.
     */
    $num_runnables_all_ranks = floor($instance_state->getNumRunnables() / $instance_state->getNumRunners());
    /**
     * @var int $num_ranks_running_remainder
     *   The number of ranks that need to run one extra runnable.
     */
    $num_ranks_running_remainder = $instance_state->getNumRunnables() % $instance_state->getNumRunners();

    $first_rank_running_remainder = $instance_state->getNumRunners() - $num_ranks_running_remainder;
    if ($runner_rank >= $first_rank_running_remainder) {
      if ($runner_rank > $first_rank_running_remainder) {
        $start = $runner_rank * ($num_runnables_all_ranks + 1);
      } else {
        $start = $runner_rank * $num_runnables_all_ranks;
      }
      $end = (($runner_rank + 1) * ($num_runnables_all_ranks + 1) - 1);
    } else {
      $start = $runner_rank * $num_runnables_all_ranks;
      $end = (($runner_rank + 1) * $num_runnables_all_ranks) - 1;
    }


    if ($num_runnables_all_ranks === 0 && $runner_rank < $num_ranks_running_remainder) {
      $start = $runner_rank;
    }



    return [$start, $end];
  }
}
