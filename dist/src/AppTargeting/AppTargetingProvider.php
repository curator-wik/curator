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
  public function register(Application $app) {
    $app['app_targeting.app_detector'] = $app->share(function ($app) {
      return new AppDetector(
        $app['status'],
        $app,
        $app['fs_access']
        );
    });

    $app['app_targeting.drupal7'] = $app->share(function($app) {
      return new Drupal7AppTargeter($app['status'], $app['fs_access']);
    });
  }

  public function boot(Application $app) { }

}
