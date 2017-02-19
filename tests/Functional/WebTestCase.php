<?php


namespace Curator\Tests\Functional;


use Curator\AppManager;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Silex\Application;

class WebTestCase extends \Silex\WebTestCase {
  /**
   * @var AppManager $app_manager
   */
  protected $app_manager = NULL;

  public function __construct($is_standalone = FALSE) {
    parent::__construct();

    $this->app_manager = new AppManager(
      $is_standalone ? AppManager::RUNMODE_STANDALONE : AppManager::RUNMODE_EMBEDDED
    );
  }

  public function createApplication() {
    $app = $this->app_manager->createApplication();
    $app['debug'] = TRUE;
    $app['session.test'] = TRUE;
    unset($app['exception_handler']);

    $this->injectTestDependencies($app);

    return $app;
  }

  protected function injectTestDependencies(Application $app) {
    $app['persistence'] = $app->share(function(){
      return new InMemoryPersistenceMock();
    });
  }
}
