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
class DrupalUpgradeTest extends IntegrationWebTestCase {
  use WebTestCaseCpkgApplierTrait;

  protected function getTestSiteRoot() {
    return '/home/www-data/drupal-7.53';
  }

  public function getTestIntegrationConfig()
  {
    // Force autodetection of drupal 7
    return parent::getTestIntegrationConfig()->setCustomAppTargeter(null);
  }

  public function testDrupal7Upgrade() {
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
    $this->runBatchApplicationOfCpkg('/home/www-data/drupal-upgrade-test-allfiles.zip', $client);

    $this->verifyTreeIs754('7.53 source tree after updating to 7.54 does not match 7.54 source tree.');
  }

  protected function verifyTreeIs754($message) {
    $diff = `/usr/bin/diff --brief -r /home/www-data/drupal-7.53 /home/www-data/drupal-7.54 2>&1 | grep -Fv '.curator-data'`;
    $this->assertEquals(
      '',
      trim($diff),
      $message
    );

  }
}
