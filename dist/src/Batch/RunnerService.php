<?php


namespace Curator\Batch;


use Curator\Persistence\PersistenceInterface;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\AbstractRunner;
use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\ScheduledTaskInterface;

class RunnerService extends AbstractRunner {

  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var int $runner_id
   */
  protected $runner_id;

  /**
   * @var int $incompleteRunnerIds
   *   Caches the result of getIncompleteRunnerIds().
   *
   *   To avoid a race, decisions made as a result of calls to
   *   getIncompleteRunnerIds() should be working on the same information as the
   *   report of pending Runners sent back to the client.
   */
  protected $incompleteRunnerIds = NULL;

  public function __construct(PersistenceInterface $persistence, StatusService $status_service) {
    $this->persistence = $persistence;
    $status = $status_service->getStatus();
    // Provide reasonably frequent feedback to user if flush() isn't available.
    $target_seconds = ($status->flush_works ? 30 : 12);
    $alarm_signal_works = $status->alarm_signal_works;

    // The controller is not an injected dependency and is not passed to the
    // parent constructor because Pimple does not detect the bidirectional
    // controller<->service dependency and fails with infinite recursion.
    parent::__construct($target_seconds, $alarm_signal_works);
  }

  /**
   * @param int $runner_id
   */
  public function setRunnerId($runner_id) {
    $this->runner_id = $runner_id;
  }

  /**
   * @return int
   */
  public function getRunnerId() {
    return $this->runner_id;
  }

  /**
   * @param int $task_id
   * @return ScheduledTaskInterface
   */
  public function loadTask($task_id) {
    if ($task_id === FALSE) {
      return NULL;
    }
    else {
      $this->persistence->beginReadOnly();
      $task_serialization = $this->persistence->get("BatchTask.$task_id");
      $this->persistence->popEnd();
      /**
       * @var ScheduledTaskInterface $scheduled_task
       */
      $scheduled_task = unserialize($task_serialization);
      return $scheduled_task;
    }
  }

  /**
   * @return int[]
   */
  public function getIncompleteRunnerIds() {
    if ($this->incompleteRunnerIds === NULL) {
      $this->incompleteRunnerIds = [];
      $task_id = $this->scheduled_task->getTaskId();
      $this->persistence->beginReadOnly();
      for ($i = 1; $i <= $this->scheduled_task->getNumRunners(); $i++) {
        if ($this->persistence->get("BatchTask.$task_id.Runner.$i", -1) !== 'done') {
          $this->incompleteRunnerIds[] = $i;
        }
      }
      $this->persistence->popEnd();
    }
    return $this->incompleteRunnerIds;
  }

  protected function finalizeTask(RunnableResultAggregatorInterface $aggregator, $runner_id) {
    $this->persistence->beginReadWrite();
    $this->taskCompleteCleanup_persistence();
    $this->persistence->end();
  }

  protected function finalizeRunner($new_result_data, RunnableInterface $last_processed_runnable = NULL, $runner_id, RunnableResultAggregatorInterface $aggregator = NULL) {
    $this->persistence->beginReadWrite();
    if ($this->task->supportsUnaryPartialResult()) {
      $this->persistUnaryPartialResult($new_result_data);
    } else {
      $this->appendReducedResult($new_result_data);
    }
    $task_id = $this->scheduled_task->getTaskId();
    $runner_state = ($last_processed_runnable === NULL ? 'done' : $last_processed_runnable->getId());
    $this->persistence->set("BatchTask.$task_id.Runner.$runner_id", $runner_state);
    $this->persistence->end();
  }

  protected function retrieveRunnerState() {
    $runner_id = $this->getRunnerId();
    $task_id = $this->scheduled_task->getTaskId();
    $this->persistence->beginReadOnly();
    $result['last_completed_runnable_id'] = $this->persistence->get("BatchTask.$task_id.Runner.$runner_id");
    $result['incomplete_runner_ids'] = $this->getIncompleteRunnerIds();

    if ($this->task->supportsUnaryPartialResult()) {
      // TODO: this is fatally flawed? The same partial unaries will be combined multiple times by concurrent runners?
      $r = $this->persistence->get("BatchTask.$task_id.ReducedResults.1");
      if ($r !== NULL) {
        $result['partial_result'] = unserialize($r);
      } else {
        $result['partial_result'] = NULL;
      }
    }
    $this->persistence->end();

    return $result;
  }

  protected function retrieveAllResultData() {
    $this->persistence->beginReadOnly();
    $result = [];
    $task_id = $this->scheduled_task->getTaskId();

    if ($this->task->supportsUnaryPartialResult()) {
      foreach ($this->scheduled_task->getRunnerIds() as $runner_id) {
        $partial = $this->persistence->get("BatchTask.$task_id.Runner.$runner_id.PartialResult", NULL);
        if ($partial !== NULL) {
          $result[] = $partial;
        }
      }
    } else {
      $count = $this->persistence->get("BatchTask.$task_id.ReducedResults.Count", 0);
      for ($i = 1; $i <= $count; $i++) {
        $str = $this->persistence->get("BatchTask.$task_id.ReducedResults.$i");
        $result[] = unserialize($str);
      }
    }

    $this->persistence->end();
    return $result;
  }

  /**
   * Precondition: $this->persistence is write locked.
   */
  protected function taskCompleteCleanup_persistence() {
    $task_id = $this->scheduled_task->getTaskId();
    $count = $this->persistence->get("BatchTask.$task_id.ReducedResults.Count", 0);
    for ($i = 1; $i <= $count; $i++) {
      $this->persistence->set("BatchTask.$task_id.ReducedResults.$i", NULL);
    }
    $this->persistence->set("BatchTask.$task_id.ReducedResults.Count", NULL);

    $runner_ids = $this->scheduled_task->getRunnerIds();
    foreach ($runner_ids as $runner_id) {
      $this->persistence->set("BatchTask.$task_id.Runner.$runner_id", NULL);
      $this->persistence->set("BatchTask.$task_id.Runner.$runner_id.PartialResult", NULL);
    }

    $this->persistence->set("BatchTask.$task_id", NULL);
  }

  /**
   * Precondition: $this->persistence is write locked.
   *
   * @param $result
   *   The result data to persist.
   */
  protected function appendReducedResult($result) {
    $task_id = $this->scheduled_task->getTaskId();
    $count = $this->persistence->get("BatchTask.$task_id.ReducedResults.Count", 0);
    $count++;
    $str = serialize($result);
    $this->persistence->set("BatchTask.$task_id.ReducedResults.$count", $str);
    $this->persistence->set("BatchTask.$task_id.ReducedResults.Count", $count);
  }

  protected function persistUnaryPartialResult($partial_result) {
    $task_id = $this->scheduled_task->getTaskId();
    $runner_id = $this->getRunnerId();
    $this->persistence->set("BatchTask.$task_id.Runner.$runner_id.PartialResult", $partial_result);
  }

  /**
   * Precondition: $this->persistence is locked.
   * @return mixed[]
   */
  protected function getReducedResults() {
    $task_id = $this->scheduled_task->getTaskId();
    $count = $this->persistence->get("BatchTask.$task_id.ReducedResults.Count", 0);
    if ($count == 0) {
      return [];
    }

    $results = [];
    for ($i = 1; $i <= $count; $i++) {
      $str = $this->persistence->get("BatchTask.$task_id.ReducedResults.$i");
      if ($str !== NULL) {
        $results[] = unserialize($str);
        unset($str);
      }
    }

    return $results;
  }

}
