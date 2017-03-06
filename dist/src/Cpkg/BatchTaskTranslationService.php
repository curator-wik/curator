<?php


namespace Curator\Cpkg;
use Curator\AppTargeting\AppDetector;
use Curator\AppTargeting\TargeterInterface;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Persistence\PersistenceInterface;
use mbaynton\BatchFramework\TaskSchedulerInterface;

/**
 * Class BatchTaskTranslationService
 *   Evaluates a cpkg archive structure and builds the necessary batch tasks
 *   to apply the archive.
 */
class BatchTaskTranslationService {

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
   * @var DeleteRenameBatchTask $delete_rename_task
   */
  protected $delete_rename_task;

  public function __construct(
    AppDetector $app_detector,
    CpkgReaderInterface $cpkg_reader,
    TaskGroupManager $task_group_mgr,
    TaskScheduler $task_scheduler,
    PersistenceInterface $persistence,
    DeleteRenameBatchTask $delete_rename_task
  ) {
    $this->app_detector = $app_detector;
    $this->cpkg_reader = $cpkg_reader;
    $this->task_group_mgr = $task_group_mgr;
    $this->task_scheduler = $task_scheduler;
    $this->persistence = $persistence;
    $this->delete_rename_task = $delete_rename_task;
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
  public function makeBatchTasks($path_to_cpkg) {
    $app_targeter = $this->app_detector->getTargeter();
    $this->cpkg_reader->validateCpkgStructure($path_to_cpkg);
    $this->validateCpkgIsApplicable($path_to_cpkg);

    // Find the versions we'll upgrade through.
    $versions = [$this->cpkg_reader->getVersion($path_to_cpkg)];
    $prev_versions_reversed = array_reverse($this->cpkg_reader->getPrevVersions($path_to_cpkg));
    while (current($prev_versions_reversed) !== $app_targeter->getCurrentVersion()) {
      $versions[] = array_shift($prev_versions_reversed);
    }
    // Put in order that upgrades must be applied.
    $versions = array_reverse($versions);

    // Assemble a Task Group to capture all tasks in the required order.
    $this->persistence->beginReadWrite();
    /**
     * @var \Curator\Batch\TaskGroup $group
     */
    $group = $this->task_group_mgr->makeNewGroup(
      sprintf('Update %s from %s to %s',
        $this->cpkg_reader->getApplication($path_to_cpkg),
        $app_targeter->getCurrentVersion(),
        $this->cpkg_reader->getVersion($path_to_cpkg))
    );

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
        $num_runners = $this->delete_rename_task->getRunnerCount($path_to_cpkg, $version);
        $task_id = $this->task_scheduler->assignTaskInstanceId();
        $del_rename_task = new CpkgBatchTaskInstanceState(
          'cpkg.delete_rename_batch_task',
          $task_id,
          $num_runners,
          $num_renames + $num_deletes,
          $path_to_cpkg,
          $version
        );
        $this->task_group_mgr->appendTaskInstance($group, $del_rename_task);
      }
    }

    $this->task_scheduler->scheduleGroupInSession($group);
    $this->persistence->end();

    return $group;
  }

  protected function validateCpkgIsApplicable($cpkg_path) {
    $cpkg_application = $this->cpkg_reader->getApplication($cpkg_path);
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

    if ($this->cpkg_reader->getVersion($cpkg_path) === $current_version) {
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
