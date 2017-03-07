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
use Symfony\Component\HttpFoundation\Response;

class DeleteRenameBatchTest extends WebTestCase {

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
    // This test class has no unauthenticated tests.
    Session::makeSessionAuthenticated($this->app['session']);
  }

  /**
   * - Perform a job with both deletes and renames, ensure all of both types
   *   are processed.
   * - Cause there to be enough runnables to require > 1 runner incarnation.
   * - Verify all changes were made to filesystem at end.
   */
  public function disabled_testMultipleDeletesAndRenames() {
    // Set mock fs contents
    $this->fs_contents->directories = [
      '/app', '/app/renames', '/app/deleteme-dir'
    ];

    $this->fs_contents->files = [
      '/app/deleteme-file' => 'hello world',
      '/app/deleteme-dir/file' => 'hello world',
      '/app/changelog.1.2.4' => 'More better.',
    ];

    for($i = 1; $i <= 30; $i++) {
      $this->fs_contents->files["/app/renames/fileA$i"] = $i;
    }

    $taskgroup = $this->scheduleCpkg('multiple-deletes-renames.zip');
    $this->assertEquals(
      2,
      count(array_unique($taskgroup->taskIds)),
      'Two batch tasks with unique ids are created from multiple-deletes-renames.zip'
    );

    /**
     * @var TaskGroupManager $taskgroup_manager
     */
    $taskgroup_manager = $this->app['batch.taskgroup_manager'];
    while ($curr_task = $taskgroup_manager->getActiveTaskInstance($taskgroup)) {
      $runner_ids = $curr_task->getRunnerIds();
      $this->assertGreaterThan(0, count($runner_ids));

      foreach ($runner_ids as $runner_id) {
        $runner_done = FALSE;
        while (!$runner_done) {
          $client = self::createClient();
          $client->request('POST', self::ENDPOINT_BATCH_RUNNER,
            [],
            [],
            [
              'HTTP_X-Runner-Id' => $runner_id
            ]);

          $response = $client->getResponse();
          $messages = $this->decodeBatchResponseContent($response->getContent());
          $last_message = end($messages);
          if ($last_message->type == BatchRunnerMessage::TYPE_RESPONSE) {
            $runner_done = TRUE;
          }
          else {
            if ($last_message->type == BatchRunnerMessage::TYPE_CONTROL) {
              $runner_done = ! $last_message->again;
            }
          }
        }
      }
    }

    $this->assertEquals(
      ['/app', '/app/renames'],
      $this->fs_contents->directories
    );

    $expected_files = [];
    for ($i = 1; $i <= 30; $i++) {
      $expected_files["/app/renames/fileB$i"] = $i;
    }
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
