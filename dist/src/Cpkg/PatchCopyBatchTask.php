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
    if ($last_processed_runnable_id === NULL) {
      $start = $runner_rank * $instance_state->getNumRunnables();
    } else {
      $start = $last_processed_runnable_id + 1;
    }

    return new PatchCopyBatchRunnableIterator(
      $this->fs_access,
      $start
    );
  }
}
