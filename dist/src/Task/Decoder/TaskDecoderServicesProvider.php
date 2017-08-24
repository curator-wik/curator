<?php


namespace Curator\Task\Decoder;


use Silex\Application;
use Silex\ServiceProviderInterface;

class TaskDecoderServicesProvider implements ServiceProviderInterface {

  public function register(Application $app) {
    $app['task.decoder.update'] = $app->share(function($app) {
      return new UpdateTaskDecoder($app['batch.taskgroup_manager'], $app['batch.task_scheduler']);
    });

    /* Not currently using this.
    $app['task.decoder.initialize_hmac_secret'] = $app->share(function($app) {
      return new InitializeHmacSecret($app['persistence'], $app['session']);
    });
    */
  }

  public function boot(Application $app) { }
}
