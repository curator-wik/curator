<?php


namespace Curator\Tests\Functional\Task\Decoder;


use Curator\AppManager;
use Curator\IntegrationConfig;

class InitializeHmacSecretTest extends \PHPUnit_Framework_TestCase {
  /**
   * @var AppManager $app_manager
   */
  protected $app_manager;

  public function setUp() {
    parent::setUp();

    $this->app_manager = new InitializeHmacSecretAppManager(AppManager::RUNMODE_EMBEDDED);
  }

  public function testInitializeHmacSecret() {
    // This test basically emulates an integration script, and requests a new
    // HMAC secret.
    $config = new IntegrationConfig();
    $config->taskIs()
      ->setIntegrationSecret(FALSE);

    $this->app_manager->applyIntegrationConfig($config);
    $secret = $this->app_manager->run();
    $this->assertNotEmpty($secret);
    $this->assertTrue(is_string($secret), 'New HMAC secret is a string.');

    // Now commit it to Curator's persistence.
    $config->taskIs()->setIntegrationSecret(TRUE);
    $this->assertTrue(
      $this->app_manager->run(),
      'Requesting commit of HMAC secret produces TRUE'
    );
  }
}
