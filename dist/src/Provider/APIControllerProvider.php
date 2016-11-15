<?php


namespace Curator\Provider;

use Silex\ControllerProviderInterface;
use Silex\Application;

class APIControllerProvider implements ControllerProviderInterface {

  public function connect(Application $app) {
    $app->get('/status', function() { return 'wip...'; });
  }

}
