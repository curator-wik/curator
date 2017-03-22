<?php


namespace Curator\Tests\Shared\Traits\Cpkg;

use Curator\APIModel\v1\BatchRunnerMessage;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Persistence\PersistenceInterface;
use Curator\Tests\Functional\MockedTimeRunnerService;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Shared\Mocks\AppTargeterMock;
use Silex\Application;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


trait WebTestCaseCpkgApplierTrait {
  private $ENDPOINT_BATCH_RUNNER = '/api/v1/batch/runner';

  /**
   * @param $cpkg_path
   *   Path to cpkg used for test.
   *
   * @return TaskGroup
   */
  protected function scheduleCpkg($cpkg_path) {
    /**
     * @var BatchTaskTranslationService $translation_svc
     */
    $translation_svc = $this->app['cpkg.batch_task_translator'];
    return $translation_svc->makeBatchTasks($this->p($cpkg_path));
  }

  protected function runBatchApplicationOfCpkg($cpkg_path, $num_tasks = NULL) {
    $taskgroup = $this->scheduleCpkg($cpkg_path);
    if ($num_tasks != NULL) {
      $this->assertEquals(
        $num_tasks,
        count(array_unique($taskgroup->taskIds)),
        'Unexpected number of tasks scheduled from ' . $cpkg_path
      );
    }

    /********* End setup, begin execution of client requests as necessary *****/
    $this->app['session']->save();

    /**
     * @var TaskGroupManager $taskgroup_manager
     */
    $taskgroup_manager = $this->app['batch.taskgroup_manager'];
    $prev_task = NULL;
    // Seed the requests to the batch controller by looking up the runner ids
    // of the first Task. In real world, will need to be pulled from an API.
    $curr_task = $taskgroup_manager->getActiveTaskInstance($taskgroup);
    $incomplete_runner_ids = $curr_task->getRunnerIds();
    $this->assertEquals(4, count($incomplete_runner_ids));

    while (count($incomplete_runner_ids)) {
      shuffle($incomplete_runner_ids);
      $runner_id = reset($incomplete_runner_ids);
      $client = $this->client;
      $client->request('POST', $this->ENDPOINT_BATCH_RUNNER,
        [],
        [],
        [
          'HTTP_X-Runner-Id' => $runner_id
        ]);

      $response = $client->getResponse();
      $messages = $this->decodeBatchResponseContent($response->getContent());

      // Ensure no errors reported in update messages.
      foreach ($messages as $message) {
        if ($message->type === BatchRunnerMessage::TYPE_UPDATE) {
          $this->assertTrue(
            $message->ok,
            sprintf('BatchRunnerUpdateMessage indicated failure: %s', implode(' | ', $message->chatter))
          );
        }
      }

      $last_message = end($messages);
      if ($last_message->type == BatchRunnerMessage::TYPE_RESPONSE || $last_message->type == BatchRunnerMessage::TYPE_CONTROL) {
        // This may be for a subsequent task, if the current one got done.
        $incomplete_runner_ids = $last_message->incomplete_runner_ids;
      } else {
        throw new \RuntimeException('Last message in batch response was not TYPE_RESPONSE or TYPE_CONTROL.');
      }
    }
  }
}
