<?php


namespace Curator\Tests\Integration;


use Curator\IntegrationConfig;
use Curator\Tests\Functional\WebTestCase;
use Silex\Application;

class IntegrationWebTestCase extends WebTestCase {

  public function setUp() {
    parent::setUp();

    /**
     * @var IntegrationConfig $integration_config
     */
    $integration_config = $this->app['integration_config'];
    $this->app['fs_access']->setWriteWorkingPath($integration_config->getSiteRootPath());
  }

  /**
   * Fully override the parent's dependency injection. We'll use our own,
   * test-double free environment for integration tests.
   */
  protected function injectTestDependencies(Application $app) {
    $app['fs_access.read_adapter'] = $app->raw('fs_access.read_adapter.filesystem');
    $app['fs_access.write_adapter'] = $app->raw('fs_access.write_adapter.filesystem');
  }

  /**
   * @return \Symfony\Component\BrowserKit\Client|\Symfony\Component\HttpKernel\Client
   */
  protected function getWebClient() {
    if ($this->client === NULL) {
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
    return $this->client;
  }

}
