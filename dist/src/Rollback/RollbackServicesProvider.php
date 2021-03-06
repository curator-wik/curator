<?php


namespace Curator\Rollback;


use Curator\CuratorApplication;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RollbackServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {
    $app['rollback'] = $app->share(function($app) {
      return new RollbackCaptureService($app['fs_access'], $app['fs_access.rollback'], $app['app_targeting.app_detector']);
    });

    $app['rollback.no-op'] = $app->share(function($app) {
      return new RollbackCaptureNoOpService();
    });

    $app['rollback.cleanup_rollback_batch_task'] = $app->share(function($app) {
      return new CleanupRollbackBatchTask($app['fs_access.rollback']);
    });

    $app['rollback.rollback_initiator_service'] = $app->share(function($app) {
      return new RollbackInitiatorService(
        $app['persistence'],
        $app['status'],
        $app['batch.taskgroup_manager'],
        $app['batch.task_scheduler'],
        $app['cpkg.batch_task_translator'],
        $app['rollback']
        );
    });
  }

  public function boot(Application $app) { }
}
