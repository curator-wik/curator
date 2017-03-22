<?php


namespace Curator\Tests\Integration\Cpkg;

use Curator\Tests\Functional\Cpkg\CpkgWebTestCase;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Integration\IntegrationWebTestCase;
use Curator\Tests\Shared\Traits\Cpkg\WebTestCaseCpkgApplierTrait;

/**
 * Class DrupalUpgradeTest
 *   Runs a complete Drupal 7.53 -> 7.54 upgrade, and verifies the result
 *   against another copy of Drupal 7.54 in the test container.
 */
class DrupalUpgradeTest extends IntegrationWebTestCase {
  use WebTestCaseCpkgApplierTrait;

  public function testDrupal7Upgrade() {
    $this->runBatchApplicationOfCpkg('/root/drupal-upgrade-test.zip');
  }
}
