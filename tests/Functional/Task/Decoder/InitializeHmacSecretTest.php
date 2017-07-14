<?php


namespace Curator\Tests\Functional\Task\Decoder;


use Curator\AppManager;
use Curator\IntegrationConfig;

/**
 * Class InitializeHmacSecretTest
 *   This class is classified as Functional because it does not use mocks for
 *   the dependencies of the system in test. But, it also does not use the usual
 *   WebTestCase because it does not provide a means to cover AppManager::run().
 */
class InitializeHmacSecretTest extends \PHPUnit_Framework_TestCase {

  public function setUp() {
    parent::setUp();
    $this->markTestSkipped('HMAC secret features are currently unutilized and may be removed.');
  }

  protected function _testInitializeHmacSecret($persistence_type) {
    // This test basically emulates an integration script, and requests a new
    // HMAC secret.
    $config = new IntegrationConfig();
    $config->taskIs()
      ->setIntegrationSecret(FALSE);

    $app_manager = new InitializeHmacSecretAppManager(AppManager::RUNMODE_EMBEDDED, $persistence_type);
    $app_manager->applyIntegrationConfig($config);
    $secret = $app_manager->run();
    $this->assertNotEmpty($secret);
    $this->assertTrue(is_string($secret), 'New HMAC secret is a string.');

    // Now commit it to Curator's persistence.
    $config->taskIs()->setIntegrationSecret(TRUE);
    $this->assertTrue(
      $app_manager->run(),
      'Requesting commit of HMAC secret produces TRUE'
    );
  }

  public function testInitializeHmacSecret_persisted() {
    $this->_testInitializeHmacSecret('memory mock');
  }

  public function testInitializeHmacSecret_sessioned() {
    $this->_testInitializeHmacSecret('broken');
  }

}
