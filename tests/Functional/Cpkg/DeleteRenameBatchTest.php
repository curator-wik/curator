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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class DeleteRenameBatchTest extends WebTestCase {

  const ENDPOINT_BATCH_RUNNER = '/api/v1/batch/runner';

  /**
   * @var \Symfony\Component\HttpKernel\Client $client
   */
  protected $client;

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
  }

  /**
   * - Perform a job with both deletes and renames, ensure all of both types
   *   are processed.
   * - Cause there to be enough runnables to require > 1 runner incarnation.
   * - Verify all changes were made to filesystem at end.
   */
  public function testMultipleDeletesAndRenames() {
    // Set mock fs contents
    $this->fs_contents->directories = [
      'renames', 'deleteme-dir'
    ];

    $this->fs_contents->files = [
      'deleteme-file' => 'hello world',
      'deleteme-dir/file' => 'hello world',
      'changelog.1.2.4' => 'More better.',
    ];

    for($i = 1; $i <= 30; $i++) {
      $this->fs_contents->files["renames/fileA$i"] = $i;
    }

    $taskgroup = $this->scheduleCpkg('multiple-deletes-renames.zip');
    $this->assertEquals(
      2,
      count(array_unique($taskgroup->taskIds)),
      'Two batch tasks with unique ids are created from multiple-deletes-renames.zip'
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
    $this->assertGreaterThan(0, count($incomplete_runner_ids));

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
      $last_message = end($messages);
      if ($last_message->type == BatchRunnerMessage::TYPE_RESPONSE || $last_message->type == BatchRunnerMessage::TYPE_CONTROL) {
        // This may be for a subsequent task, if the current one got done.
        $incomplete_runner_ids = $last_message->incomplete_runner_ids;
      } else {
        throw new \RuntimeException('Last message in batch response was not TYPE_RESPONSE or TYPE_CONTROL.');
      }
    }

    $this->assertEquals(
      ['renames'],
      $this->fs_contents->directories
    );

    $expected_files = [];
    for ($i = 1; $i <= 30; $i++) {
      $expected_files["renames/fileB$i"] = $i;
    }
    $expected_files['changelog.1.2.5'] = 'More better.';
    ksort($expected_files, SORT_STRING);
    ksort($this->fs_contents->files, SORT_STRING);
    $this->assertEquals($expected_files, $this->fs_contents->files);
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
}
