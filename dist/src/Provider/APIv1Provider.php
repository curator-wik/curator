<?php


namespace Curator\Provider;

use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\Application;

class APIv1Provider implements ControllerProviderInterface {

  public function connect(Application $app) {
    $c = $app['controllers_factory'];
    $c->get('/status', 'Curator\APIController\v1\StatusController::handleRequest');

    return $c;
  }

}
