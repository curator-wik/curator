<?php


namespace Curator\Cpkg;


use Silex\Application;
use Silex\ServiceProviderInterface;

class CpkgServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {

    $app['cpkg.reader'] = $app->share(function($app) {
      return new CpkgReader();
    });

    $app['cpkg.delete_rename_batch_task'] = $app->share(function($app) {
      return new DeleteRenameBatchTask(
        $app['cpkg.reader'],
        $app['fs_access'],
        $app['batch.task_scheduler'],
        $app['rollback'],
        $app['rollback.no-op']
      );
    });

    $app['cpkg.patch_copy_batch_task'] = $app->share(function($app) {
      return new PatchCopyBatchTask(
        $app['cpkg.reader'],
        $app['fs_access'],
        $app['batch.task_scheduler'],
        $app['rollback'],
        $app['rollback.no-op']
      );
    });

    $app['cpkg.batch_task_translator'] = $app->share(function($app) {
      return new BatchTaskTranslationService(
        $app['status'],
        $app['app_targeting.app_detector'],
        $app['cpkg.reader'],
        $app['batch.taskgroup_manager'],
        $app['batch.task_scheduler'],
        $app['persistence'],
        $app['cpkg.delete_rename_batch_task']
      );
    });

  }

  public function boot(Application $app) { }
}
