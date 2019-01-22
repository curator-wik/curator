<?php


namespace Curator\Batch;


use Curator\Persistence\PersistenceInterface;
use mbaynton\BatchFramework\ScheduledTask;
use mbaynton\BatchFramework\TaskInterface;
use mbaynton\BatchFramework\TaskSchedulerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class TaskScheduler
 * Service id: batch.task_scheduler
 */
class TaskScheduler {

  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var SessionInterface $session
   */
  protected $session;

  public function __construct(PersistenceInterface $persistence, SessionInterface $session) {
    $this->persistence = $persistence;
    $this->session = $session;
  }

  public function scheduleGroupInSession(TaskGroup $group) {
    $group->ownerSession = $this->session->getId();
    $this->persistence->beginReadWrite();
    // Even if previously serialized, we need to update the owner session.
    $this->persistence->set(sprintf('BatchTaskGroup.%d', $group->taskGroupId), serialize($group));

    // Append to the current session's task group queue.
    $session_queue = $this->session->get('TaskGroupQueue', []);
    if (! in_array($group->taskGroupId, $session_queue)) {
      array_push($session_queue, $group->taskGroupId);
    }
    $this->session->set('TaskGroupQueue', $session_queue);
    $this->persistence->popEnd();
  }

  /**
   * Removes a group from the Session.
   *
   * Must happen when all Tasks in the Group have been completed.
   *
   * @param \Curator\Batch\TaskGroup $group
   *   The group to remove from the Session
   * @param bool $delete_group
   *   Whether to also remove the group's serialization from persistence.
   *   This only occurs if the session did in fact own the group.
   */
  public function removeGroupFromSession(TaskGroup $group, $delete_group = TRUE) {
    if (count($group->taskIds) > 0) {
      throw new \LogicException('Invalid precondition: Group must contain no Tasks when removed from session.');
    }
    $session_queue = $this->session->get('TaskGroupQueue', []);
    $group_ix = array_search($group->taskGroupId, $session_queue);
    if ($group_ix !== FALSE) {
      array_splice($session_queue, $group_ix, 1);
      $this->session->set('TaskGroupQueue', $session_queue);
    }

    if ($delete_group && $this->session->getId() === $group->ownerSession) {
      $this->persistence->beginReadWrite();
      $this->persistence->set(sprintf('BatchTaskGroup.%d', $group->taskGroupId), NULL);
      $this->persistence->popEnd();
    }
  }

  /**
   * Gets the TaskGroup currently up for execution by the container's session.
   *
   * @return TaskGroup|null
   *   The session's current TaskGroup, or NULL if no TaskGroups are scheduled.
   */
  public function getCurrentGroupInSession() {
    $taskgroup_ids = $this->session->get('TaskGroupQueue', []);
    $current_taskgroup_id = reset($taskgroup_ids);
    if ($current_taskgroup_id !== FALSE) {
      /**
       * @var TaskGroup $group
       */
      $this->persistence->beginReadOnly();
      $group = unserialize($this->persistence->get(sprintf('BatchTaskGroup.%d', $current_taskgroup_id)));
      if ($group !== NULL && $group->ownerSession !== $this->session->getId()) {
        // The TaskGroup was stolen from us.
        // Other Runners are very likely to concurrently encounter this.
        $this->persistence->beginReadWrite();
        $reread_taskgroup_ids = $this->session->get('TaskGroupQueue', []);
        if ($current_taskgroup_id === reset($reread_taskgroup_ids)) {
          // We're the first runner to notice. Remove this TaskGroup from queue.
          while ($group !== NULL && $group->ownerSession !== $this->session->getId()) {
            array_shift($reread_taskgroup_ids);
            $current_taskgroup_id = reset($reread_taskgroup_ids);
            if ($current_taskgroup_id !== FALSE) {
              $group = unserialize($this->persistence->get(sprintf('BatchTaskGroup.%d', $current_taskgroup_id)));
            } else {
              $group = NULL;
            }
          }

          $this->session->set('TaskGroupQueue', $reread_taskgroup_ids);
          $this->session->save();
        }
        $this->persistence->popEnd();
      }
      $this->persistence->popEnd();
      return $group;
    } else {
      return NULL;
    }
  }

  public function assignTaskInstanceId() {
    $this->persistence->beginReadWrite();
    $new_task_id = $this->persistence->get('BatchTask.NextId', 1);
    $this->persistence->set('BatchTask.NextId', $new_task_id + 1);
    $this->persistence->popEnd();
    return $new_task_id;
  }

  /**
   * Assigns new unique runner ids to a task instance.
   *
   * Takes out an exclusive persistence lock with popEnd().
   *
   * @param \Curator\Batch\TaskInstanceState $task_instance
   *   The task instance requiring runner ids.
   * @throws \RuntimeException
   *   If the TaskIntanceState given has already been assigned runner ids.
   */
  public function assignRunnerIdsToTaskInstance(TaskInstanceState $task_instance) {
    if ($task_instance->getRunnerIds() !== NULL) {
      throw new \RuntimeException('Runner IDs have already been assigned.');
    }
    $this->persistence->beginReadWrite();
    $next_available_id = $this->persistence->get('BatchRunner.NextId', 1);
    $new_next = $next_available_id + $task_instance->getNumRunners();
    $this->persistence->set('BatchRunner.NextId', $new_next);
    $this->persistence->popEnd();

    $id_array = [];
    for ($id = $next_available_id; $id < $new_next; $id++) {
      $id_array[] = $id;
    }
    $task_instance->setRunnerIds($id_array);
  }
}
