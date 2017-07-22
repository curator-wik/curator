<?php


namespace Curator\Task\Decoder;


use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Download\CurlDownloadBatchTaskInstanceState;
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

  public function __construct(TaskGroupManager $task_group_manager, TaskScheduler $task_scheduler) {
    $this->taskGroupManager = $task_group_manager;
    $this->taskScheduler = $task_scheduler;
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

    $download_task_instance = new CurlDownloadBatchTaskInstanceState(
      'download.cpkg_download_task',
      $this->taskScheduler->assignTaskInstanceId(),
      $task->getPackageLocation()
    );

    $this->taskGroupManager->appendTaskInstance(
      $task_group,
      $download_task_instance
    );

    $this->taskScheduler->scheduleGroupInSession($task_group);
  }
}
