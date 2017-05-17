<?php


namespace Curator\Tests\Functional\Task\Decoder;


use Curator\AppManager;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;

/**
 * Class InitializeHmacSecretAppManager
 *   A modified AppManager that simply injects in-memory persistence.
 */
class InitializeHmacSecretAppManager extends AppManager {
  public function createApplication() {
    $app = parent::createApplication();

    $app['persistence'] = $app->share(function($app) {
      return new InMemoryPersistenceMock();
    });
    return $app;
  }
}
