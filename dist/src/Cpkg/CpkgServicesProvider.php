<?php


namespace Curator\Cpkg;


use Silex\Application;
use Silex\ServiceProviderInterface;

class CpkgServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {

    $app['cpkg.reader'] = $app->share(function($app) {
      return new CpkgReader(
        $app['fs_access.read_adapter'],
        $app['fs_access.write_adapter']
      );
    });

    $app['cpkg.classifier'] = $app->share(function($app) {
      return new CpkgClassificationService($app['cpkg.reader']);
    });

    $app['cpkg.delete_rename_batch_task'] = $app->share(function($app) {
      return new DeleteRenameBatchTask(
        $app['cpkg.reader'],
        $app['fs_access'],
        $app['batch.task_scheduler'],
        $app['batch.taskgroup_manager'],
        $app['rollback'],
        $app['rollback.no-op'],
        $app['rollback.rollback_initiator_service'],
        $app['cpkg.classifier']
      );
    });

    $app['cpkg.patch_copy_batch_task'] = $app->share(function($app) {
      return new PatchCopyBatchTask(
        $app['cpkg.reader'],
        $app['fs_access'],
        $app['batch.task_scheduler'],
        $app['batch.taskgroup_manager'],
        $app['rollback'],
        $app['rollback.no-op'],
        $app['rollback.rollback_initiator_service']
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
        $app['cpkg.classifier']
      );
    });

  }

  public function boot(Application $app) { }
}
