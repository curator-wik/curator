<?php


namespace Curator\Cpkg;


use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\RollbackCaptureNoOpService;
use Curator\Rollback\RollbackCaptureService;
use Curator\Rollback\RollbackInitiatorService;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

/**
 * Class DeleteRenameBatchTask
 *   Performs the deletions and renames prescribed by one version increment of
 *   a cpkg.
 */
class DeleteRenameBatchTask extends CpkgBatchTask {

  /**
   * @var CpkgClassificationService $cpkg_classifier
   */
  protected $cpkg_classifier;

  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * DeleteRenameBatchTask constructor.
   */
  public function __construct(CpkgReader $reader, FSAccessManager $fs_access, TaskScheduler $scheduler, TaskGroupManager $taskGroupManager, RollbackCaptureService $rollback, RollbackCaptureNoOpService $null_rollback, RollbackInitiatorService $rollback_initiator, CpkgClassificationService $cpkg_classifier) {
    parent::__construct($reader, $scheduler, $taskGroupManager, $rollback, $null_rollback, $rollback_initiator);
    $this->fs_access = $fs_access;
    $this->cpkg_classifier = $cpkg_classifier;
  }

  public function getRunnerCount($cpkg_path, $version) {
    return $this->cpkg_classifier->getRunnerCountDeleteRename($cpkg_path, $version);
  }

  public function getRunnableIterator(TaskInstanceStateInterface $instance_state, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    /**
     * @var CpkgBatchTaskInstanceState $instance_state
     */
    if ($last_processed_runnable_id === NULL) {
      $start = $runner_rank;
    } else {
      $start = $last_processed_runnable_id + $instance_state->getNumRunners();
    }

    // An empty rollback capture path is set to indicate that no rollback
    // should be captured, such as when actually applying previously captured
    // rollback cpkgs.
    $rollback_svc = $instance_state->getRollbackPath() === '' ?
      $this->null_rollback : $this->rollback;

    return new DeleteRenameBatchRunnableIterator(
      $this->fs_access,
      $rollback_svc,
      $this->reader->getDeletes($instance_state->getCpkgPath(), $instance_state->getVersion()),
      $this->reader->getRenames($instance_state->getCpkgPath(), $instance_state->getVersion()),
      $start,
      $instance_state->getNumRunners()
    );
  }

}
