<?php


namespace Curator\Batch;


use Curator\Persistence\PersistenceInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class TaskScheduler implements TaskSchedulerInterface {

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

  public function scheduleTask(TaskInterface $task) {
    $this->persistence->beginReadWrite();
    $new_task_id = $this->persistence->get('BatchTask.NextId', 0);
    $scheduled_task = new ScheduledTask(
      $task,
      $new_task_id,
      $this->session->getId()
    );

    $this->persistence->set("BatchTask.$new_task_id", serialize($scheduled_task));
    $this->persistence->set('BatchTask.NextId', $new_task_id++);
    $this->persistence->end();

    $updated_session_queue = $this->session->get('BatchTaskQueue', []);
    array_push($updated_session_queue, $new_task_id);
    $this->session->set('BatchTaskQueue', $updated_session_queue);

    return $scheduled_task;
  }
}
