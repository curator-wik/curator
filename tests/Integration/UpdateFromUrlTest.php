<?php


namespace Curator\Tests\Integration;


use Curator\AppManager;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskScheduler;
use Curator\IntegrationConfig;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Shared\Mocks\AppTargeterMock;
use Curator\Tests\Shared\Traits\Batch\WebTestCaseBatchRunnerTrait;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Client;

/**
 * Class UpdateFromUrlTest
 * This class covers:
 * - Decoding a Task\UpdateTask (Task\Decoder\UpdateTaskDecoder) to schedule a
 *   cpkg for download;
 * - That completion of the download triggers scheduling of a task group to
 *   apply the cpkg.
 *
 * It is in the Integration namespace because it depends on the PHP development
 * webserver inside the docker container, but extends Functional\WebTestCase
 * to allow the cpkg's changes to be applied in a mocked FSAccess layer.
 */
class UpdateFromUrlTest extends WebTestCase {
  use WebserverRunnerTrait;
  use WebTestCaseBatchRunnerTrait;

  /**
   * @var Client $client;
   */
  protected $client;

  public function setUp() {
    parent::setUp();
    /**
     * @var SessionInterface $session
     */
    $session = $this->app['session'];
    // This test class has no unauthenticated tests.
    Session::makeSessionAuthenticated($session);

    $this->client = self::createClient();
    $cj = $this->client->getCookieJar();
    $session_cookie = new Cookie($this->app['session']->getName(), $this->app['session']->getId());
    $cj->set($session_cookie);

    if (self::$h_server_proc === FALSE) {
      $this->fail('Test fails because development webserver is not operational.');
    }
  }

  protected static function installDownloadData() {
    copy(__DIR__ . '/../Unit/fixtures/cpkgs/multiple-files-patches.zip', '/tmp/oh_look_an_update.cpkg.zip');
  }

  protected function getTestIntegrationConfig() {
    $integration_config = parent::getTestIntegrationConfig();
    $integration_config
      ->taskIs()->update('MockApp')
      ->fromPackage(getenv('TEST_HTTP_SERVER') . 'oh_look_an_update.cpkg.zip');

    $integration_config->setCustomAppTargeter(new AppTargeterMock());

    return $integration_config;
  }

  public function testUpdateFromCpkgAtUrl() {
    // Before making the first client request directly to Curator, verify that
    // the Integration Config (provided in test via getTestIntegrationConfig())
    // was correctly translated to a batch task to download a cpkg.
    // $task_scheduler = new TaskScheduler($this->app['persistence'], $this->app['session']);
    $task_scheduler = $this->app['batch.task_scheduler'];
    $task_group = $task_scheduler->getCurrentGroupInSession();
    $this->assertNotNull($task_group);

    $this->assertInstanceOf(
      '\Curator\Download\CurlDownloadBatchTaskInstanceState',
      $this->app['batch.taskgroup_manager']->getActiveTaskInstance($task_group)
    );

    // Close session so the request pipeline can start it successfully.
    /**
     * @var SessionInterface $session
     */
    $session = $this->app['session'];
    $session->save();
    $this->runBatchTasks($this->client, $task_group, FALSE);

    // This should have resulted in a downloaded cpkg that enqueued a new
    // TaskGroup to update MockApp from 1.2.3 to 1.2.4.
    /**
     * @var TaskGroup $task_group
     */
    $task_group = $task_scheduler->getCurrentGroupInSession();
    $this->assertNotNull($task_group);
    $this->assertEquals(
      'Update MockApp from 1.2.3 to 1.2.4',
      $task_group->friendlyDescription
      );
  }
}
