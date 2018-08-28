<?php

namespace Curator\APIModel\v1;

/**
 * Class BatchTaskInfoModel
 * Information about a batch task, such as the current one, that is of use to clients.
 */
class BatchTaskInfoModel
{
  public function __construct($taskGroupDescription, $runnerIds, $numRunners, $numRunnables, $taskGroupId, $numTasksInGroup)
  {
    $this->friendlyName = $taskGroupDescription;
    $this->runnerIds = $runnerIds;
    $this->numRunners = $numRunners;
    $this->numRunnables = $numRunnables;
    $this->taskGroupId = $taskGroupId;
    $this->numTasksInGroup = $numTasksInGroup;
  }

  /**
   * @var int $taskGroupId
   *   The internal id number that Curator is using to track the current task group.
   *   Useful to clients for detecting when a new task group has started.
   */
  public $taskGroupId;

  /**
   * @var string $friendlyName
   *   A friendly description of the task group that the current task is a member of.
   */
  public $friendlyName = '';

  /**
   * @var int $numTasksInGroup
   *   The number of tasks in the current task group.
   *   Useful to clients for estimating overall completion of a task group.
   */
  public $numTasksInGroup = 0;

  /**
   * @var int[] $runnerIds
   *   The runner ids that have one or more un-executed runnables.
   */
  public $runnerIds = [];

  /**
   * @var int $numRunners
   *   The maximum number of runners that the client may send concurrent requests for.
   */
  public $numRunners = 0;

  /**
   * @var int $numRunnables
   *   The approximate number of total runnables that comprise the entire task.
   */
  public $numRunnables = 0;
}