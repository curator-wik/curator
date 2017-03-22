<?php


namespace Curator\Tests\Functional;


use Curator\AppManager;
use Curator\IntegrationConfig;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;
use Silex\Application;

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
    parent::__construct();

    $this->app_manager = new AppManager(
      $is_standalone ? AppManager::RUNMODE_STANDALONE : AppManager::RUNMODE_EMBEDDED
    );

    $this->fs_contents = new MockedFilesystemContents();
  }

  public function createApplication() {
    $integration = clone IntegrationConfig::getNullConfig();
    $integration->setSiteRootPath($this->getTestSiteRoot());
    $this->app_manager->applyIntegrationConfig($integration);
    $app = $this->app_manager->createApplication();

    $app['debug'] = TRUE;
    $app['session.test'] = TRUE;
    unset($app['exception_handler']);

    $this->injectTestDependencies($app);

    return $app;
  }

  protected function getTestSiteRoot() {
    return '/app';
  }

  public function setUp() {
    parent::setUp();
    $this->fs_contents->clearAll();
    $this->fs_contents->directories = ['/app'];
  }

  protected function injectTestDependencies(Application $app) {
    $app['persistence'] = $app->share(function(){
      return new InMemoryPersistenceMock();
    });

    $app['fs_access.read_adapter'] = $app->share(function($app) {
      $adapter = new ReadAdapterMock('/app');
      $adapter->setFilesystemContents($this->fs_contents);
      return $adapter;
    });

    $app['fs_access.write_adapter'] = $app->share(function($app) {
      $adapter = new WriteAdapterMock('/app');
      $adapter->setFilesystemContents($this->fs_contents);
      return $adapter;
    });
  }
}
