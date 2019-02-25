<?php


namespace Curator\Tests\Functional;


use Curator\AppManager;
use Curator\IntegrationConfig;
use Curator\Persistence\SessionFauxPersistence;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;
use Silex\Application;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class WebTestCase extends \Silex\WebTestCase {
  /**
   * @var AppManager $app_manager
   */
  protected $app_manager = NULL;

  /**
   * @var MockedFilesystemContents $fs_contents
   */
  protected $fs_contents = NULL;

  public function __construct($is_standalone = FALSE) {
    $this->fs_contents = new MockedFilesystemContents();

    $serviceOverrides = $this->injectTestDependencies();

    $this->app_manager = new FunctionalTestAppManager(
      $is_standalone ? AppManager::RUNMODE_STANDALONE : AppManager::RUNMODE_EMBEDDED,
      $serviceOverrides
    );

    parent::__construct();
  }

  protected function injectTestDependencies() {
    $deps = [
      'persistence' => [
        function(){
          return new InMemoryPersistenceMock();
        },
        TRUE
      ],
      'fs_access.read_adapter' => [
        function($app) {
          $adapter = new ReadAdapterMock('/app');
          $adapter->setFilesystemContents($this->fs_contents);
          return $adapter;
        },
        TRUE
      ],
      'fs_access.write_adapter' => [
        function($app) {
          $adapter = new WriteAdapterMock('/app');
          $adapter->setFilesystemContents($this->fs_contents);
          return $adapter;
        },
        TRUE
      ],
    ];

    $deps['fs_access.read_adapter.filesystem'] = $deps['fs_access.read_adapter'];
    $deps['fs_access.write_adapter.filesystem'] = $deps['fs_access.write_adapter'];

    return $deps;
  }

  public function createApplication() {
    return NULL; // setUp() fully overridden, and we're not using this abstract method.
  }

  protected function getTestSiteRoot() {
    return '/app';
  }

  protected function getTestIntegrationConfig() {
    $integration = clone IntegrationConfig::getNullConfig();
    $integration
      ->setSiteRootPath($this->getTestSiteRoot())
      ->setRollbackCapturePath($this->getTestSiteRoot() . '/' . 'rollback')
      ->setCustomAppTargeter('\Curator\Tests\Shared\Mocks\AppTargeterMock::factory');
    return $integration;
  }

  public function setUp() {
    // Now that the DI container is set up, we can safely ask it to do whatever
    // the integration config might trigger.
    $this->fs_contents->clearAll();
    $this->fs_contents->directories = ['/app'];
    $integration = $this->getTestIntegrationConfig();
    // This will create the application within the app_manager
    $this->app_manager->applyIntegrationConfig($integration);

    $this->app = $this->app_manager->getApplication();
    // Modify the DI container for testing.
    $this->app['debug'] = TRUE;
    $this->app['session.test'] = TRUE;
    unset($this->app['exception_handler']);

    // Make sure the session is started.
    /**
     * @var SessionInterface $session
     */
    $session = $this->app['session'];
    $session->start();
  }
}
