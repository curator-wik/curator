<?php

namespace Curator\Tests\Unit;

use Curator\AppManager;
use Curator\CuratorApplication;
use Curator\IntegrationConfig;

class CuratorApplicationTest extends \PHPUnit_Framework_TestCase  {
  public function testCuratorApplicationInstantiates() {
    new CuratorApplication(IntegrationConfig::getNullConfig(), AppManager::singleton());
  }
}
