<?php

namespace Curator\Tests\Unit;

use Curator\AppManager;
use Curator\CuratorApplication;
use Curator\IntegrationConfig;

class CuratorApplicationTest extends \PHPUnit\Framework\TestCase  {
  public function testCuratorApplicationInstantiates() {
    /**
     * @var AppManager $app_manager
     */
    $app_manager = require __DIR__ . '/../../../dist/web/index.php';
    $app = $app_manager->createApplication();
    $this->assertInstanceOf('Curator\CuratorApplication', $app);
  }
}
