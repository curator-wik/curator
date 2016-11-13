<?php


namespace Curator\Tests\Integration\Persistence;

use Curator\AppManager;
use Curator\CuratorApplication;
use Curator\FSAccess\FSAccessInterface;
use Curator\IntegrationConfig;
use Curator\Tests\SharedTraits\Persistence\PersistenceTestsTrait;

class PersistenceTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var CuratorApplication $appContainer
   */
  protected $appContainer;

  const TEST_PATH = '/home/ftptest/www';

  public function setUp() {
    parent::setUp();

    $integration_config = new IntegrationConfig();
    $integration_config->setSiteRootPath(self::TEST_PATH);
    $this->appContainer = new CuratorApplication($integration_config, AppManager::singleton());
    // Always test file persistence for now, until there's others
    $this->appContainer['persistence'] = $this->appContainer->raw('persistence.file');

    // Seed the FSAccessManager
    /**
     * @var FSAccessInterface $fs
     */
    $fs = $this->appContainer['fs_access'];
    $fs->setWorkingPath(self::TEST_PATH);
    $fs->setWriteWorkingPath(self::TEST_PATH);
  }

  protected function sutFactory() {
    return $this->appContainer['persistence'];
  }

  use PersistenceTestsTrait;
}
