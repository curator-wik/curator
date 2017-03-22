<?php


namespace Curator\Tests\Integration;


use Curator\Tests\Functional\WebTestCase;
use Silex\Application;

class IntegrationWebTestCase extends WebTestCase {

  /**
   * Fully override the parent's dependency injection. We'll use our own,
   * test-double free environment for integration tests.
   */
  protected function injectTestDependencies(Application $app) {
    $app['fs_access.read_adapter'] = $app->raw('fs_access.read_adapter.filesystem');
    $app['fs_access.write_adapter'] = $app->raw('fs_access.write_adapter.filesystem');
  }

}
