<?php


namespace Curator\Rollback;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Cpkg\CpkgReaderInterface;
use Curator\Persistence\PersistenceInterface;
use Curator\Status\StatusService;

/**
 * Class RollbackInitiatorService
 *   Creates and schedules batch tasks to perform a rollback.
 *
 * Service id: rollback.rollback_initiator_service
 */
class RollbackInitiatorService
{
  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var StatusService $status_service
   */
  protected $status_service;

  /**
   * @var TaskGroupManager $task_group_mgr
   */
  protected $task_group_mgr;

  /**
   * @var \Curator\Batch\TaskScheduler $task_scheduler
   */
  protected $task_scheduler;

  /**
   * @var BatchTaskTranslationService $cpkg_to_tasks_service
   */
  protected $cpkg_to_tasks_service;

  /**
   * @var RollbackCaptureService $rollback
   */
  protected $rollback;

  public function __construct(
    PersistenceInterface $persistence,
    StatusService $status_service,
    TaskGroupManager $task_group_mgr,
    TaskScheduler $task_scheduler,
    BatchTaskTranslationService $cpkg_to_tasks_service,
    RollbackCaptureService $rollback
  )
  {
    $this->persistence = $persistence;
    $this->status_service = $status_service;
    $this->task_group_mgr = $task_group_mgr;
    $this->task_scheduler = $task_scheduler;
    $this->cpkg_to_tasks_service = $cpkg_to_tasks_service;
    $this->rollback = $rollback;
  }

  /**
   * @param string $rollback_capture_path
   *   Optional location to rollback capture data.
   *   System capture location is used if not provided.
   * @param TaskGroup $task_group
   *   Optional TaskGroup to append tasks to.
   *   A new TaskGroup is created if not provided.
   * @return TaskGroup
   */
  public function makeBatchTasks($rollback_capture_path = '', $task_group = NULL) {
    $this->persistence->beginReadWrite();
    if ($task_group === NULL) {
      $task_group = $this->task_group_mgr->makeNewGroup('Roll back');
    }

    // First we need to fully cpkgize the rollback capture location.
    $this->rollback->fixupToCpkg($rollback_capture_path);

    // Then use the Cpkg\BatchTaskTranslationService to append the rollback tasks.
    $captureRollback = FALSE;
    $this->cpkg_to_tasks_service->makeBatchTasks($rollback_capture_path, $task_group, $captureRollback);

    $cleanup_task = new DoRollbackBatchTaskInstanceState(
      $this->task_scheduler->assignTaskInstanceId(),
      $rollback_capture_path,
      'rollback.cleanup_rollback_batch_task'
    );
    $this->task_group_mgr->appendTaskInstance($task_group, $cleanup_task);

    $this->task_scheduler->scheduleGroupInSession($task_group);
    $this->persistence->popEnd();

    return $task_group;
  }
}