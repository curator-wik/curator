<?php


namespace Curator\Download;


use Curator\CuratorApplication;
use Silex\Application;
use Silex\ServiceProviderInterface;

class DownloadServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {
    $app['download.curl_download_batch_task'] = $app->share(function($app) {
      return new CurlDownloadBatchTask($app['status']);
    });

    $app['download.cpkg_download_batch_task'] = $app->share(function($app) {
      return new CpkgDownloadBatchTask($app['status'], $app['cpkg.batch_task_translator'], $app['rollback']);
    });
  }

  public function boot(Application $app) { }
}
