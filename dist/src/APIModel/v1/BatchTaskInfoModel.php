<?php

namespace Curator\APIModel\v1;

/**
 * Class BatchTaskInfoModel
 * Information about a batch task, such as the current one, that is of use to clients.
 */
class BatchTaskInfoModel
{
  public function __construct($taskGroupDescription, $runnerIds, $numRunners, $numRunnables)
  {
    $this->friendlyName = $taskGroupDescription;
    $this->runnerIds = $runnerIds;
    $this->numRunners = $numRunners;
    $this->numRunnables = $numRunnables;
  }

  /**
   * @var string $friendlyName
   *   A friendly description of the task group that the current task is a member of.
   */
  public $friendlyName = '';

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