<?php


namespace Curator\Tests\Shared\Mocks;


use Curator\AppTargeting\TargeterInterface;

class AppTargeterMock implements TargeterInterface {
  public function getAppName() {
    return 'MockApp';
  }

  public function getCurrentVersion() {
    return '1.2.3';
  }

  public function getVariantTags() {
    return [];
  }

  public static function factory() {
    return new AppTargeterMock();
  }
}
