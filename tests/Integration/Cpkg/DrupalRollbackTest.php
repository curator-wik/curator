<?php


namespace Curator\Tests\Integration\Cpkg;

use Curator\IntegrationConfig;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Integration\IntegrationWebTestCase;
use Curator\Tests\Shared\Traits\Cpkg\WebTestCaseCpkgApplierTrait;
use Silex\Application;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\Client;

/**
 * Class DrupalUpgradeTest
 *   Runs a complete Drupal 7.53 -> 7.54 upgrade, and verifies the result
 *   against another copy of Drupal 7.54 in the test container.
 */
class DrupalRollbackTest extends IntegrationWebTestCase {
  use WebTestCaseCpkgApplierTrait;

  protected function getTestSiteRoot() {
    return '/home/www-data/drupal-7.54_rollback';
  }

  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();

    // Make a copy of Drupal 7.54 that we can begin updating and roll back.
    `cp -a /home/www-data/drupal-7.54 /home/www-data/drupal-7.54_rollback`;
  }

  public function getTestIntegrationConfig()
  {
    // Force autodetection of drupal 7
    return parent::getTestIntegrationConfig()->setCustomAppTargeter(null);
  }

  /**
   * Sets permissions to make updating fail, then attempts to update a copy of
   * Drupal 7.54 to 7.60. The reference copy of Drupal 7.54 at /home/www-data/drupal-7.54
   * is left untouched. The attempted update should be fully reverted by the
   * rollback mechanism so /home/www-data/drupal-7.54 and /home/www-data/drupal-7.54_rollback
   * should again match at the end.
   */
  public function testDrupal7Rollback() {
    $client = self::createClient();
    /**
     * @var SessionInterface $session
     */
    $session = $this->app['session'];
    // This test class has no unauthenticated tests.
    Session::makeSessionAuthenticated($session);

    $cj = $client->getCookieJar();
    $session_cookie = new Cookie($this->app['session']->getName(), $this->app['session']->getId());
    $cj->set($session_cookie);

    $this->assertTrue(
      chmod($this->getTestSiteRoot() . DIRECTORY_SEPARATOR . 'modules/system', 0000),
      'Failed to set up permissions problem, test run would be invalid.'
      );
    $runner_request_count = $this->runBatchApplicationOfCpkg('/home/www-data/Drupal7.54-7.60.cpkg.zip', $client);
    $this->assertGreaterThan(1, $runner_request_count, 'Number of batch runner requests to complete the upgrade seems too low.');
    $this->verifyTreeIs754('7.54 source tree after update to 7.60 that should have failed did not roll back to match 7.54 source tree.');
  }

  protected function verifyTreeIs754($message) {
    $diff = `/usr/bin/diff --brief -r /home/www-data/drupal-7.54_rollback /home/www-data/drupal-7.54 2>&1 | grep -Fv '.curator-data'`;
    $this->assertEquals(
      '',
      trim($diff),
      $message
    );

  }
}
