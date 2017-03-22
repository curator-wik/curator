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

abstract class CpkgWebTestCase extends WebTestCase {
  use WebTestCaseCpkgApplierTrait;

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

    $this->runBatchApplicationOfCpkg($cpkg_path, $num_tasks);

    $this->assertEquals($expected_dirs, $this->fs_contents->directories);

    ksort($expected_files, SORT_STRING);
    ksort($this->fs_contents->files, SORT_STRING);
    $this->assertEquals($expected_files, $this->fs_contents->files);
  }

}
