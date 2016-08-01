<?php

namespace Curator\AppTargeting;


use Pimple\ServiceProviderInterface;

/**
 * Class AppTargetingProvider
 *
 * Provides default services for application targeting to the container.
 *
 * @package Curator\AppTargeting
 */
class AppTargetingProvider implements ServiceProviderInterface {

  /**
   * @inheritdoc
   */
  public function register(Container $c) {
    $c['app_targeting.app_detector'] = function ($c) {
      return new Detector();
    };
  }
}
