<?php


namespace Curator\Tests\Unit\AppTargeting;


use Curator\AppTargeting\Drupal7AppTargeter;
use Curator\FSAccess\FSAccessManager;
use Curator\Tests\Shared\Mocks\StatusServiceMock;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class Drupal7AppTargeterTest extends \PHPUnit_Framework_TestCase {
  protected function setupVersionDetectionTest($files) {
    $fs_contents = new MockedFilesystemContents();
    $fs_contents->clearAll();
    $fs_contents->directories = ['includes', 'modules/system'];
    $fs_contents->files = $files;

    $read_adapter = new ReadAdapterMock('/app');
    $read_adapter->setFilesystemContents($fs_contents);
    $write_adapter = new WriteAdapterMock('/app');
    $write_adapter->setFilesystemContents($fs_contents);

    $fs = new FSAccessManager($read_adapter, $write_adapter);
    $fs->setWorkingPath('/app');

    $sut = new Drupal7AppTargeter(new StatusServiceMock(), $fs);
    return $sut;
  }

  public function testVersionDetectionBySystemInfo() {
    $system_info = <<<HEREDOC
name = System
description = Handles general site configuration for administrators.
package = Core
version = VERSION
core = 7.x
files[] = system.archiver.inc
files[] = system.mail.inc
files[] = system.queue.inc
files[] = system.tar.inc
files[] = system.updater.inc
files[] = system.test
required = TRUE
configure = admin/config/system

; Information added by Drupal.org packaging script on 2016-12-07
version = "7.53"
project = "drupal"
datestamp = "1481152423"

HEREDOC;

    $sut = $this->setupVersionDetectionTest(['modules/system/system.info' => $system_info]);
    $this->assertEquals('7.53', $sut->getCurrentVersion());
  }

  public function testVersionDetectionByBootstrapInc() {
    $bootstrap_inc = <<<HEREDOC
<?php

/**
 * @file
 * Functions that need to be loaded on every Drupal request.
 */

/**
 * The current system version.
 */
define('VERSION', '7.53');

/**
 * Core API compatibility.
 */
define('DRUPAL_CORE_COMPATIBILITY', '7.x');

HEREDOC;

    $sut = $this->setupVersionDetectionTest(['includes/bootstrap.inc' => $bootstrap_inc]);
    $this->assertEquals('7.53', $sut->getCurrentVersion());
  }

  public function testVersionDetectionOddBootstrapInc() {
    $sut = $this->setupVersionDetectionTest(['includes/bootstrap.inc' => 'define ( "VERSION" , "7.53-foo" );']);
    $this->assertEquals('7.53-foo', $sut->getCurrentVersion());
  }
}
