<?php


namespace Curator\Cpkg;
use Curator\AppTargeting\AppDetector;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Persistence\PersistenceInterface;
use Curator\Rollback\DoRollbackBatchTaskInstanceState;
use Curator\Status\StatusService;

/**
 * Class BatchTaskTranslationService
 *   Evaluates a cpkg archive structure and builds the necessary batch tasks
 *   to apply the archive.
 *
 * DI ID: cpkg.batch_task_translator
 */
class BatchTaskTranslationService {

  protected $status_service;

  /**
   * @var AppDetector $app_detector
   */
  protected $app_detector;

  /**
   * @var CpkgReaderInterface $cpkg_reader
   */
  protected $cpkg_reader;

  /**
   * @var TaskGroupManager $task_group_mgr
   */
  protected $task_group_mgr;

  /**
   * @var \Curator\Batch\TaskScheduler $task_scheduler
   */
  protected $task_scheduler;

  /**
   * @var \Curator\Persistence\PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var CpkgClassificationService $classifier
   */
  protected $classifier;

  public function __construct(
    StatusService $status_service,
    AppDetector $app_detector,
    CpkgReaderInterface $cpkg_reader,
    TaskGroupManager $task_group_mgr,
    TaskScheduler $task_scheduler,
    PersistenceInterface $persistence,
    CpkgClassificationService $classifier
  ) {
    $this->status_service = $status_service;
    $this->app_detector = $app_detector;
    $this->cpkg_reader = $cpkg_reader;
    $this->task_group_mgr = $task_group_mgr;
    $this->task_scheduler = $task_scheduler;
    $this->persistence = $persistence;
    $this->classifier = $classifier;
  }

  /**
   * @param string $path_to_cpkg
   *
   * @return TaskGroup
   *   The new TaskGroup created from the cpkg.
   *
   * @throws \UnexpectedValueException
   *   When the $path_to_cpkg does not reference a valid cpkg archive.
   * @throws \InvalidArgumentException
   *   When the cpkg does not contain upgrades for the application.
   */
  public function makeBatchTasks($path_to_cpkg, TaskGroup $group = NULL) {
    $app_targeter = $this->app_detector->getTargeter();
    $this->cpkg_reader->validateCpkgStructure($path_to_cpkg);
    $this->validateCpkgIsApplicable($path_to_cpkg);

    // Find the versions we'll upgrade through.
    $versions = [$this->cpkg_reader->getVersion($path_to_cpkg)];
    $prev_versions_reversed = array_reverse($this->cpkg_reader->getPrevVersions($path_to_cpkg));
    while (current($prev_versions_reversed) !== $app_targeter->getCurrentVersion() && count($prev_versions_reversed)) {
      $versions[] = array_shift($prev_versions_reversed);
    }
    // Put in order that upgrades must be applied.
    $versions = array_reverse($versions);

    // Assemble a Task Group to capture all tasks in the required order.
    $this->persistence->beginReadWrite();
    if ($group === NULL) {
      $group = $this->task_group_mgr->makeNewGroup(
        sprintf('Update %s from %s to %s',
          $this->cpkg_reader->getApplication($path_to_cpkg),
          $app_targeter->getCurrentVersion(),
          $this->cpkg_reader->getVersion($path_to_cpkg))
      );
    }

    $rollback_path = $this->status_service->getStatus()->rollback_capture_path;

    foreach ($versions as $version) {
      /*
       * Up to two tasks may be scheduled per version, in this order, depending on
       * the contents of the cpkg:
       * 1. Deletions and renames
       * 2. Verbatim file writes and patches
       */
      $num_renames = count($this->cpkg_reader->getRenames($path_to_cpkg, $version));
      $num_deletes = count($this->cpkg_reader->getDeletes($path_to_cpkg, $version));
      if ($num_renames + $num_deletes > 0) {
        $num_runners = $this->classifier->getRunnerCountDeleteRename($path_to_cpkg, $version);
        $task_id = $this->task_scheduler->assignTaskInstanceId();
        $del_rename_task = new CpkgBatchTaskInstanceState(
          'cpkg.delete_rename_batch_task',
          $task_id,
          $num_runners,
          $num_renames + $num_deletes,
          $path_to_cpkg,
          $version,
          $rollback_path
        );
        $this->task_group_mgr->appendTaskInstance($group, $del_rename_task);
      }

      $patch_copy_iterator = PatchCopyBatchRunnableIterator::buildPatchCopyInternalIterator(
        $this->cpkg_reader->getReaderPrimitives($path_to_cpkg),
        $version
      );
      $patch_copy_runnables = 0;
      foreach ($patch_copy_iterator as $item) {
        $patch_copy_runnables++;
      }

      if ($patch_copy_runnables > 0) {
        $task_id = $this->task_scheduler->assignTaskInstanceId();
        $patch_copy_task = new CpkgBatchTaskInstanceState(
          'cpkg.patch_copy_batch_task',
          $task_id,
          4,
          $patch_copy_runnables,
          $path_to_cpkg,
          $version,
          $rollback_path
        );

        $this->task_group_mgr->appendTaskInstance($group, $patch_copy_task);
      }
    }

    // If there is a failure, the whole TaskGroup gets unscheduled by
    // CpkgBatchTask::assembleResultResponse. Otherwise, clean up the rollback
    // location at the end.
    $cleanup_task = new DoRollbackBatchTaskInstanceState(
      $this->task_scheduler->assignTaskInstanceId(),
      $rollback_path,
      'rollback.cleanup_rollback_batch_task'
    );
    $this->task_group_mgr->appendTaskInstance($group, $cleanup_task);


    $this->task_scheduler->scheduleGroupInSession($group);
    $this->persistence->popEnd();

    return $group;
  }

  protected function validateCpkgIsApplicable($cpkg_path) {
    $cpkg_application = $this->cpkg_reader->getApplication($cpkg_path);
    $version_in_cpkg = $this->cpkg_reader->getVersion($cpkg_path);

    // Do not perform these validations if the cpkg looks like a rollback.
    if ($cpkg_application === 'Curator_Rollback' && $version_in_cpkg == 'rollback') {
      return;
    }

    $app_targeter = $this->app_detector->getTargeter();
    if (strcasecmp($cpkg_application, $app_targeter->getAppName()) !== 0) {
      throw new \InvalidArgumentException(
        sprintf('The update package is for "%s", but you are running %s.',
          $cpkg_application,
          $app_targeter->getAppName()
        )
      );
    }

    $current_version = (string) $app_targeter->getCurrentVersion();

    if ($version_in_cpkg === $current_version) {
      throw new \InvalidArgumentException(sprintf('The update package provides version "%s", but it is already installed.', $current_version));
    }

    $prev_versions = $this->cpkg_reader->getPrevVersions($cpkg_path);
    if (! in_array($current_version, $prev_versions)) {
      if (count($prev_versions) == 1) {
        $supported_range = 'version ' . reset($prev_versions);
      } else {
        $supported_range = sprintf('versions %s through %s', reset($prev_versions), end($prev_versions));
      }
      throw new \InvalidArgumentException(
        sprintf('The update package does not contain updates to your version of %s. You are running version %s; the package updates %s.',
          $app_targeter->getAppName(),
          $app_targeter->getCurrentVersion(),
          $supported_range
        )
      );
    }
  }
}
