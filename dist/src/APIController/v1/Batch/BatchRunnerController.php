<?php


namespace Curator\APIController\v1\Batch;


use Curator\APIModel\v1\BatchRunnerControlMessage;
use Curator\APIModel\v1\BatchRunnerResponseMessage;
use Curator\APIModel\v1\BatchRunnerUpdateMessage;
use Curator\Batch\BatchRunnerResponse;
use Curator\Batch\DescribedRunnableInterface;
use Curator\Batch\RunnerService;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskInstanceState;
use Curator\Batch\TaskScheduler;
use Curator\CuratorApplication;
use Curator\Persistence\PersistenceInterface;
use mbaynton\BatchFramework\Controller\HttpRunnerControllerTrait;
use mbaynton\BatchFramework\Controller\RunnerControllerInterface;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\ScheduledTaskInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

// TODO: Ensure session not held during main loop, not opened while persistence lock is held.

class BatchRunnerController implements RunnerControllerInterface {
  use HttpRunnerControllerTrait;

  /**
   * @var int CHATTER_FLUSH_INTERVAL
   *   Rate-limit update message transmission to at most one per this many
   *   microseconds.
   */
  const CHATTER_FLUSH_INTERVAL = 1e5; // 1/10th second.

  /**
   * @var TaskInstanceState $task
   */
  protected $task_instance;

  /**
   * @var ScheduledTaskInterface $scheduledTask
   */
  protected $scheduledTask;

  /**
   * @var RunnerService $runner_service
   */
  protected $runner_service;

  /**
   * @var SessionInterface $session
   */
  protected $session;

  /**
   * @var BatchRunnerResponse $runner_response
   */
  protected $runner_response;

  /**
   * @var int $last_chatter_flush
   *   Elapsed time when last BatchRunnerUpdateMessage was flushed.
   */
  protected $last_chatter_flush;

  /**
   * @var ProgressInfo $progress
   */
  protected $progress;

  public function __construct(SessionInterface $session, PersistenceInterface $persistence, RunnerService $runner_service, TaskScheduler $task_scheduler, TaskGroupManager $taskgroup_mgr) {
    $this->session = $session;
    $this->runner_service = $runner_service;

    // The Pimple service injector gives us a new RunnerService unpaired to a
    // batch runner controller. That's us, so make the coupling.
    $this->runner_service->setController($this);

    // A fake ProgressInfo for the first call to onBeforeRunnableStarted().
    $this->progress = new ProgressInfo();
    $this->progress->timeElapsed = $this->progress->runnablesExecuted = 0;
    $this->last_chatter_flush = -1 * static::CHATTER_FLUSH_INTERVAL;

    // Get the session's current TaskGroup
    /**
     * @var TaskGroup $group
     */
    $group = $task_scheduler->getCurrentGroupInSession();
    if ($group !== NULL) {
      /**
       * @var TaskInstanceState $task_instance
       */
      $this->task_instance = $taskgroup_mgr->getActiveTaskInstance($group);
    } else {
      $this->task_instance = NULL;
    }
  }

  public function handleRequest(Request $request, CuratorApplication $app_container) {
    $runner_id = $this->getRunnerId($request);
    if ($runner_id === NULL) {
      throw new BadRequestHttpException('X-Runner-Id header required.');
    }
    $this->runner_service->setRunnerId($runner_id);

    if ($this->task_instance === NULL) {
      // No pending tasks for our session, just tell them not to call back.
      return new BatchRunnerResponse([
        new BatchRunnerControlMessage($runner_id, FALSE)
      ]);
    } else {
      /**
       * @var BatchRunnerResponse $runner_response
       */
      $this->runner_response = new BatchRunnerResponse();

      /**
       * @var Response $response
       */
      $response = $this->runner_service->run(
        $app_container[$this->task_instance->getTaskServiceName()],
        $this->task_instance
      );

      if ($response !== NULL) {
        // Task done, send final outcome.
        $this->runner_response->postMessage(
          new BatchRunnerResponseMessage($response)
        );
      } else {
        // Task will finish during a subsequent request. Call back.
        $this->runner_response->postMessage(
          new BatchRunnerControlMessage($runner_id, TRUE)
        );
      }

      return $this->runner_response;
    }
  }

  /**
   * Streams progress messages over the BatchRunnerResponse chunked socket.
   *
   * @param RunnableInterface $runnable
   */
  public function onBeforeRunnableStarted(RunnableInterface $runnable) {
    $message = new BatchRunnerUpdateMessage();
    $message->n = $this->progress->runnablesExecuted;
    if ($runnable instanceof DescribedRunnableInterface) {
      /**
       * @var DescribedRunnableInterface $runnable
       */
      $message->chatter[0] = $runnable->describe();
    }
    $this->runner_response->postMessage($message);

    if ($this->progress->timeElapsed - $this->last_chatter_flush >= static::CHATTER_FLUSH_INTERVAL) {
      $this->runner_response->flush();
      $this->last_chatter_flush = $this->progress->timeElapsed;
    }
  }

  public function onRunnableComplete(RunnableInterface $runnable, $result, ProgressInfo $progress) {
    $this->progress = $progress;
  }

  public function onRunnableError(RunnableInterface $runnable, $exception, ProgressInfo $progress) {
    $this->progress = $progress;

    $message = new BatchRunnerUpdateMessage();
    $message->ok = FALSE;
    if ($runnable instanceof DescribedRunnableInterface) {
      /**
       * @var DescribedRunnableInterface $runnable
       */
      $message->chatter[0] = $runnable->describe();
    }
    $this->runner_response->postMessage($message);
  }

  public function onTaskComplete(ScheduledTaskInterface $task) {
    $batch_task_ids = $this->session->get('BatchTaskQueue', []);
    $this->session->set('BatchTaskQueue', array_shift($batch_task_ids));
  }

  protected function getRunnerId(Request $request) {
    return $request->headers->get('X-Runner-Id');
  }

}
