<?php


namespace Curator\Provider;

use Curator\Controller\IntegrationUtils;
use Silex\Application;
use Silex\ControllerProviderInterface;

class MiscRoutes implements ControllerProviderInterface {
  public function connect(Application $app) {
    $this->registerServiceControllers($app);

    $c = $app['controllers_factory'];

    $c->get('/integration-utils/init-secret', 'controller.integration_utils:initHmacSecret');

    return $c;
  }

  protected function registerServiceControllers(Application $app) {
    $app['controller.integration_utils'] = $app->share(function ($app) {
      return new IntegrationUtils();
    });
  }
}
