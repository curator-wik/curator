<?php

namespace Curator\Batch;

/**
 * Class TaskGroup
 *   Model for a set of batch tasks that must be executed in order to complete
 *   a larger whole objective.
 *
 *   Use operations in TaskGroupManager to manipulate the values.
 */
class TaskGroup {
  /**
   * @var int $taskGroupId
   */
  public $taskGroupId;

  /**
   * @var int[] $taskIds
   */
  public $taskIds;

  /**
   * @var string $ownerSession
   *   Session id associated to runner connections.
   */
  public $ownerSession;

  /**
   * @var string $friendlyDescription
   */
  public $friendlyDescription;


}