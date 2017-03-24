<?php


namespace Curator\Tests\Integration\Cpkg;

use Curator\CuratorApplication;
use Curator\IntegrationConfig;
use Curator\Tests\Functional\Cpkg\CpkgWebTestCase;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Integration\IntegrationWebTestCase;
use Curator\Tests\Shared\Traits\Cpkg\WebTestCaseCpkgApplierTrait;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Class DrupalUpgradeTest
 *   Runs a complete Drupal 7.53 -> 7.54 upgrade, and verifies the result
 *   against another copy of Drupal 7.54 in the test container.
 */
class DrupalUpgradeTest extends IntegrationWebTestCase {
  use WebTestCaseCpkgApplierTrait;

  protected function injectTestDependencies(CuratorApplication $app) {
    parent::injectTestDependencies($app);
    /**
     * @var IntegrationConfig $integration_config
     */
    $integration_config = $app['integration_config'];
    $integration_config->setCustomAppTargeter($app['app_targeting.drupal7']);
  }

  protected function getTestSiteRoot() {
    return '/root/drupal-7.53';
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
    $this->runBatchApplicationOfCpkg('/root/drupal-upgrade-test-allfiles.zip', $client);
  }
}
