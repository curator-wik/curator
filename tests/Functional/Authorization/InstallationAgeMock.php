<?php


namespace Curator\Tests\Functional\Authorization;

use Curator\Authorization\InstallationAgeInterface;
use Curator\CuratorApplication;

class InstallationAgeMock implements InstallationAgeInterface {
  /**
   * @var int $mockedTime
   */
  protected $mockedTime;

  public function __construct($mocked_time) {
    $this->mockedTime = $mocked_time;
  }

  public function getInstallationTime($curator_filename) {
    return $this->mockedTime;
  }
}
