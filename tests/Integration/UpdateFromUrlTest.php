<?php


namespace Curator\Tests\Integration;


use Curator\IntegrationConfig;
use Curator\Tests\Functional\WebTestCase;

/**
 * Class UpdateFromUrlTest
 * This class covers:
 * - Decoding a Task\UpdateTask (Task\Decoder\UpdateTaskDecoder) to schedule a
 *   cpkg for download;
 * - That completion of the download triggers scheduling of a task group to
 *   apply the cpkg.
 *
 * It is in the Integration namespace because it depends on the PHP development
 * webserver inside the docker container, but extends Functional\WebTestCase
 * to allow the cpkg's changes to be applied in a mocked FSAccess layer.
 */
class UpdateFromUrlTest extends WebTestCase {

  protected function getTestIntegrationConfig() {
    $integration_config = parent::getTestIntegrationConfig();
    $integration_config
      ->taskIs()->update('foo')
      ->fromPackage('')

  }

  public function testUpdateFromCpkgAtUrl() {

  }
}
