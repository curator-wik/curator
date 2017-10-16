<?php


namespace Curator\Provider\APIv1;

use Curator\APIController\v1\Batch\BatchRunnerController;
use Curator\APIController\v1\ConnectionConfigController;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class AuthenticatedOrUnconfiguredEndpointsProvider implements ControllerProviderInterface {
  public function connect(Application $app) {
    $this->registerServiceControllers($app);

    /**
     * @var ControllerCollection $controllers
     */
    $controllers = $app['controllers_factory'];
    $controllers->before(function(Request $request) use ($app) {
      $app['authorization.middleware']->requireAuthenticatedOrNoAuthenticationConfigured();
    });

    $controllers->post('/config/connection/{type}', 'controller.apiv1.connection_config:handlePost')
      ->assert('type', '^[a-zA-Z0-9]$');

    $controllers->post('/batch/runner', 'controller.apiv1.batch.runner:handleRequest');

    return $controllers;
  }

  protected function registerServiceControllers(Application $app) {
    // Batch
    $app['controller.apiv1.batch.runner'] = function($app) {
      return new BatchRunnerController($app['session'], $app['persistence'], $app['batch.runner_service'], $app['batch.task_scheduler'], $app['batch.taskgroup_manager']);
    };

    // File write connection configuration
    $app['controller.apiv1.connection_config'] = function($app) {
      return new ConnectionConfigController();
    };
  }
}
