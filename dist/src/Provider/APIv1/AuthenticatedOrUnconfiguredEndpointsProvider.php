<?php


namespace Curator\Provider\APIv1;

use Curator\APIController\v1\Batch\BatchRunnerController;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class AuthenticatedOrUnconfiguredEndpointsProvider implements ControllerProviderInterface {
  public function connect(Application $app) {
    $this->registerServiceControllers($app);

    /**
     * @var ControllerCollection $batch
     */
    $batch = $app['controllers_factory'];
    $batch->before(function(Request $request) use ($app) {
      $app['authorization.middleware']->requireAuthenticatedOrNoAuthenticationConfigured();
    });
    $batch->post('/batch/runner', 'controller.apiv1.batch.runner:handleRequest');
    $batch->get('/batch/current-task', 'controller.apiv1.batch.runner:handleTaskInfoRequest');

    return $batch;
  }

  protected function registerServiceControllers(Application $app) {
    // Batch
    $app['controller.apiv1.batch.runner'] = function($app) {
      return new BatchRunnerController($app['session'], $app['persistence'], $app['batch.runner_service'], $app['batch.task_scheduler'], $app['batch.taskgroup_manager'], $app['error_handler']);
    };
  }
}
