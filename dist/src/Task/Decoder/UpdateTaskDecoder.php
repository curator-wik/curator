<?php


namespace Curator\Task\Decoder;


use Curator\Batch\TaskGroupManager;
use Curator\Task\TaskDecoderInterface;
use Curator\Task\TaskInterface;
use Curator\Task\UpdateTask;

class UpdateTaskDecoder implements TaskDecoderInterface {

  /**
   * @var TaskGroupManager
   */
  protected $taskGroupManager;

  public function __construct(TaskGroupManager $task_group_manager) {
    $this->taskGroupManager = $task_group_manager;
  }

  /**
   * @param UpdateTask $task
   */
  public function decodeTask(TaskInterface $task) {
    // Enqueue an update batch task group.
    // TODO: Make TaskGroupManager queriable enough to tell us if there's one
    // already, and do not enqueue another one then.
    $task_group = $this->taskGroupManager->makeNewGroup(
      "Update " . ucfirst($task->getComponent()) . " to " . $task->getToVersion()
    );

    $this->taskGroupManager->appendTaskInstance(
      $task_group,
      // A task state interface for a download task that has the package url.
      // The Task should respond to task completion by handing the downloaded
      // resource off to the Cpkg\BatchTaskTranslationService.
    );
  }
}
