<?php


namespace Curator\Provider\APIv1;

use Curator\APIController\v1\ProbeEnvironmentController;
use Curator\APIController\v1\StatusController;
use Silex\ControllerProviderInterface;
use Silex\Application;

class UnauthenticatedEndpointsProvider implements ControllerProviderInterface {
  public function connect(Application $app) {
    $this->registerServiceControllers($app);

    $c = $app['controllers_factory'];

    $c->get('/status', 'controller.apiv1.status:handleRequest');
    $c->get('/probe-environment', 'controller.apiv1.probe-environment:handleRequest');

    return $c;
  }

  protected function registerServiceControllers(Application $app) {
    $app['controller.apiv1.status'] = $app->share(function ($app) {
      return new StatusController($app['status']);
    });

    $app['controller.apiv1.probe-environment'] = $app->share(function ($app) {
      return new ProbeEnvironmentController($app['status']);
    });
  }
}
