<?php

namespace Curator\AppTargeting;
use Curator\CuratorApplication;
use Silex\Application;
use Silex\ServiceProviderInterface;


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
  public function register(Application $c) {
    $c['app_targeting.app_detector'] = function ($c) {
      return new Detector($c['integration_config']);
    };
  }
}
