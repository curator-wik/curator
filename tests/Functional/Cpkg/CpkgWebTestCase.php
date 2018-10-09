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
use Curator\Tests\Shared\Traits\Cpkg\WebTestCaseCpkgApplierTrait;
use Silex\Application;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Client;

abstract class CpkgWebTestCase extends WebTestCase {
  use WebTestCaseCpkgApplierTrait;

  /**
   * @var Client $client;
   */
  protected $client;

  protected function injectTestDependencies() {
    $deps = parent::injectTestDependencies();

    $deps['batch.runner_service'] = [
      function (Application $app) {
        $time_mock = $this->getMockBuilder('\mbaynton\BatchFramework\Internal\FunctionWrappers')
          ->enableProxyingToOriginalMethods()
          ->getMock();

        // Make all runnables take 5 seconds.
        $faketime = 10e9;
        $increment = 5e6;
        $time_mock->method('microtime')->willReturnCallback(function () use (&$faketime, $increment) {
          $faketime += $increment;
          return $faketime;
        });

        return new MockedTimeRunnerService($app['persistence'], $app['status'], $time_mock);
      },
      FALSE
    ];

    return $deps;
  }

  public function setUp() {
    parent::setUp(); // The application is constructed in here.

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

  protected function _testCpkgBatchApplication($cpkg_path, $initial_dirs, $expected_dirs, $initial_files, $expected_files, $num_tasks = 2) {
    // Set mock fs contents
    $this->fs_contents->directories = array_merge($this->fs_contents->directories, $initial_dirs);

    $this->fs_contents->files = array_merge($this->fs_contents->files, $initial_files);

    $this->runBatchApplicationOfCpkg($cpkg_path, $this->client, $num_tasks);

    // Rather than testing the specifics of what the rollback capture service created,
    // we'll test it functionally below by asking it to roll back.
    $dirs_less_rollback_capture = static::rollback_capture_filter($this->fs_contents->directories);
    sort($dirs_less_rollback_capture);
    sort($expected_dirs);
    $this->assertEquals($expected_dirs, $dirs_less_rollback_capture, 'Expected directories differed from actual.');

    $files_less_rollback_capture = static::rollback_capture_filter($this->fs_contents->files, TRUE);
    // Files are ksort()ed, not sort()ed, because they are indexed associatively.
    ksort($expected_files, SORT_STRING);
    ksort($files_less_rollback_capture, SORT_STRING);
    $this->assertEquals($expected_files, $files_less_rollback_capture, 'Expected files differed from actual.');

    // Now do the rollback and verify it's right.
    // $this->app['rollback']->fixupToCpkg('rollback');

  }

  protected static function rollback_capture_filter($input_array, $use_keys = FALSE) {
    $filtered_array = [];

    foreach ($input_array AS $key => $value) {
      if ($use_keys) {
        $include = strpos($key, 'rollback') !== 0;
      } else {
        $include = strpos($value, 'rollback') !== 0;
      }

      if ($include) {
        $filtered_array[$key] = $value;
      }
    }
    return $filtered_array;
  }

}
