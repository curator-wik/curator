<?php


namespace Curator\Tests\Functional\Cpkg;


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

abstract class CpkgWebTestCase extends WebTestCase {

  const ENDPOINT_BATCH_RUNNER = '/api/v1/batch/runner';

  protected function injectTestDependencies(Application $app) {
    parent::injectTestDependencies($app);

    $app['batch.runner_service'] = function (Application $app) {
      $time_mock = $this->getMockBuilder('\mbaynton\BatchFramework\Internal\FunctionWrappers')
        ->enableProxyingToOriginalMethods()
        ->getMock();

      // Make all runnables take 5 seconds.
      $faketime = 10e9;
      $increment = 5e6;
      $time_mock->method('microtime')->willReturnCallback(function() use(&$faketime, $increment) {
        $faketime += $increment;
        return $faketime;
      });

      return new MockedTimeRunnerService($app['persistence'], $app['status'], $time_mock);
    };

    // Add the mock app targeter, and make it selected.
    $app['app_targeting.mock'] = $app->share(function() {
      return new AppTargeterMock();
    });

    /**
     * @var PersistenceInterface $persistence
     */
    $persistence = $app['persistence'];
    $persistence->beginReadWrite();
    $persistence->set('adjoining_app_targeter', 'mock');
    $persistence->end();
  }

  public function setUp() {
    parent::setUp();

    $this->client = self::createClient();
    /**
     * @var SessionInterface $session
     */
    $session = $this->app['session'];
    // This test class has no unauthenticated tests.
    Session::makeSessionAuthenticated($session);

    $cj = $this->client->getCookieJar();
    $session_cookie = new Cookie($this->app['session']->getName(), $this->app['session']->getId());
    $cj->set($session_cookie);

    $this->app['fs_access']->setWriteWorkingPath('/app');
  }

  /**
   * @param $archive_name
   *   Name of a file in the cpkgs fixtures directory.
   * @return string
   *   Full path to the file.
   */
  protected function p($archive_name) {
    return __DIR__ . "/../../Unit/fixtures/cpkgs/$archive_name";
  }

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

  protected function decodeBatchResponseContent($content) {
    $objects = [];
    while(strlen($content)) {
      list($chunk_len, $content) = explode("\r\n", $content, 2);
      $chunk_len = hexdec($chunk_len) + strlen("\r\n"); // incl. trailing \r\n
      $chunk = substr($content, 0, $chunk_len);
      $objects[] = json_decode($chunk);
      $content = substr($content, $chunk_len);
    }
    return $objects;
  }

  protected function _testCpkgBatchApplication($cpkg_path, $initial_dirs, $expected_dirs, $initial_files, $expected_files, $num_tasks = 2) {
    // Set mock fs contents
    $this->fs_contents->directories = $initial_dirs;

    $this->fs_contents->files = $initial_files;

    $taskgroup = $this->scheduleCpkg($cpkg_path);
    $this->assertEquals(
      $num_tasks,
      count(array_unique($taskgroup->taskIds)),
      'Unexpected number of tasks scheduled from ' . $cpkg_path
    );

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
      $client->request('POST', self::ENDPOINT_BATCH_RUNNER,
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

    $this->assertEquals($expected_dirs, $this->fs_contents->directories);

    ksort($expected_files, SORT_STRING);
    ksort($this->fs_contents->files, SORT_STRING);
    $this->assertEquals($expected_files, $this->fs_contents->files);
  }

}
