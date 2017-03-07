<?php
/**
 * Created by PhpStorm.
 * User: mbaynton
 * Date: 2/26/17
 * Time: 3:37 PM
 */

namespace Curator\Batch;


use Curator\Persistence\PersistenceInterface;

class TaskGroupManager {

  protected $persistence;

  protected $task_scheduler;

  public function __construct(PersistenceInterface $persistence, TaskScheduler $task_scheduler) {
    $this->persistence = $persistence;
    $this->task_scheduler = $task_scheduler;
  }

  /**
   * Makes a new TaskGroup, assigning it a free id number.
   *
   * Takes out an exclusive persistence lock.
   *
   * @param string $friendlyDescription
   * @return TaskGroup
   */
  public function makeNewGroup($friendlyDescription) {
    $group = new TaskGroup();
    $group->friendlyDescription = $friendlyDescription;
    $this->persistence->beginReadWrite();
    $next_id = $this->persistence->get('BatchTaskGroup.NextId', 1);
    $this->persistence->set('BatchTaskGroup.NextId', $next_id++);
    $this->persistence->popEnd();
    $group->taskGroupId = $next_id;

    return $group;
  }

  /**
   * Adds a new task to the end of the group, ensures runner ids are assigned,
   * and persists the task state.
   *
   * Takes out an exclusive persistence lock. If you anticipate adding several
   * tasks in succession, obtain an exclusive lock outside this method for
   * better performance.
   *
   * @param \mbaynton\BatchFramework\TaskInstanceStateInterface $task_instance
   *   The task instance to add to the group.
   */
  public function appendTaskInstance(TaskGroup $group, TaskInstanceState $task_instance) {
    $this->persistence->beginReadWrite();

    // Add runner ids if they are not present.
    if ($task_instance->getRunnerIds() === NULL) {
      $this->task_scheduler->assignRunnerIdsToTaskInstance($task_instance);
    }

    $this->persistence->set(
      sprintf('BatchTask.%d', $task_instance->getTaskId()),
      serialize($task_instance)
    );
    $this->persistence->popEnd();
    $group->taskIds[] = $task_instance->getTaskId();
  }

  /**
   * Removes a task from the group and destroys its persisted task state.
   *
   * Takes out an exclusive persistence lock.
   *
   * @param \Curator\Batch\TaskGroup $group
   * @param int $task_id
   * @throws \UnexpectedValueException
   *   If the given $task_id is not a member of the $group.
   */
  public function removeTaskInstance(TaskGroup $group, $task_id) {
    if (is_int($task_id)) {
      // It'll typically be the 1st one.
      if (reset($group->taskIds) === $task_id) {
        array_shift($group->taskIds);
      } else {
        $pos = array_search($task_id, $group->taskIds);
        if ($pos === FALSE) {
          throw new \UnexpectedValueException(sprintf('Task "%s" is not in task group %d', $task_id, $group->taskGroupId));
        }
        array_splice($group->taskIds, $pos, 1);
      }
    } else {
      throw new \UnexpectedValueException('$task_id must be an integer.');
    }

    $this->persistence->beginReadWrite();
    $this->persistence->set(sprintf('BatchTask.%d', $task_id), NULL);
    $this->persistence->popEnd();
  }

  /**
   * @param \Curator\Batch\TaskGroup $group
   * @return TaskInstanceState|null
   *   The current TaskInstanceState
   */
  public function getActiveTaskInstance(TaskGroup $group) {
    $this->persistence->beginReadOnly();
    $task_id = reset($group->taskIds);
    if ($task_id !== FALSE) {
      $task_instance = $this->persistence->get(sprintf('BatchTask.%d', $task_id));
      $task_instance = unserialize($task_instance);
      $this->persistence->popEnd();
      return $task_instance;
    } else {
      return NULL;
    }
  }

  public function getActiveTaskInstanceByGroupId($taskgroup_id) {
    $this->persistence->beginReadOnly();
    $task_group = $this->_loadTaskGroupById($taskgroup_id);
    $task_instance = $this->getActiveTaskInstance($task_group);
    $this->persistence->popEnd();
    return $task_instance;
  }

  /**
   * @param int $taskgroup_id
   * @return TaskGroup
   */
  protected function _loadTaskGroupById($taskgroup_id) {
    $serialized = $this->persistence->get(sprintf('BatchTaskGroup.%d', $taskgroup_id));
    return unserialize($serialized);
  }
}
