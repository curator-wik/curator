<?php


namespace Curator\Tests\Functional\Task\Decoder;


use Curator\AppManager;
use Curator\Tests\Shared\Mocks\BrokenPersistenceMock;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;

/**
 * Class InitializeHmacSecretAppManager
 *   A modified AppManager that simply injects in-memory persistence.
 */
class InitializeHmacSecretAppManager extends AppManager {

  /**
   * @var string $persistence_type
   */
  protected $persistence_type;

  public function __construct($runmode, $persistence_type) {
    parent::__construct($runmode);
    $this->persistence_type = $persistence_type;
  }

  public function createApplication() {
    $app = parent::createApplication();

    if ($this->persistence_type == 'memory mock') {
      $app['persistence'] = $app->share(function($app) {
        return new InMemoryPersistenceMock();
      });
    } else {
      $app['persistence'] = $app->share(function($app) {
        return new BrokenPersistenceMock();
      });
    }

    return $app;
  }
}
