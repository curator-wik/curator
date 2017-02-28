<?php


namespace Curator\Batch;


use Curator\Persistence\PersistenceInterface;
use mbaynton\BatchFramework\ScheduledTask;
use mbaynton\BatchFramework\TaskInterface;
use mbaynton\BatchFramework\TaskSchedulerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
    $this->persistence->set('BatchTask.NextId', $new_task_id++);
    $this->persistence->popEnd();
    return $new_task_id;
  }
}
