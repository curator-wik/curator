<?php


namespace Curator\Task\Decoder;


use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Download\CpkgDownloadBatchTaskInstanceState;
use Curator\Download\CurlDownloadBatchTaskInstanceState;
use Curator\Status\StatusService;
use Curator\Task\TaskDecoderInterface;
use Curator\Task\TaskInterface;
use Curator\Task\UpdateTask;
use mbaynton\BatchFramework\TaskSchedulerInterface;

class UpdateTaskDecoder implements TaskDecoderInterface {

  /**
   * @var TaskGroupManager
   */
  protected $taskGroupManager;

  /**
   * @var TaskScheduler $taskScheduler
   */
  protected $taskScheduler;

  /**
   * @var StatusService $status
   */
  protected $status;

  public function __construct(TaskGroupManager $task_group_manager, TaskScheduler $task_scheduler, StatusService $status) {
    $this->taskGroupManager = $task_group_manager;
    $this->taskScheduler = $task_scheduler;
    $this->status = $status;
  }

  public function decodeTask(TaskInterface $task) {
    /**
     * @var UpdateTask $task
     */
    // Enqueue an update batch task group.
    // TODO: Make TaskGroupManager queriable enough to tell us if there's one
    // already, and do not enqueue another one then.
    $task_group = $this->taskGroupManager->makeNewGroup(
      "Download " . ucfirst($task->getComponent()) . " " . $task->getToVersion() . ' update package'
    );

    $download_task_instance = new CpkgDownloadBatchTaskInstanceState(
      $this->taskScheduler->assignTaskInstanceId(),
      $task->getPackageLocation(),
      $this->status->getStatus()->rollback_capture_path,
      'download.cpkg_download_batch_task'
    );

    $this->taskGroupManager->appendTaskInstance(
      $task_group,
      $download_task_instance
    );

    $this->taskScheduler->scheduleGroupInSession($task_group);
  }
}
