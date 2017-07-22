<?php


namespace Curator\Download;


use Curator\CuratorApplication;
use Silex\Application;
use Silex\ServiceProviderInterface;

class DownloadServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {
    $app['download.curl_download_task'] = $app->share(function($app) {
      return new CurlDownloadBatchTask($app['integration_config']);
    });

    $app['download.cpkg_download_batch_task'] = $app->share(function($app) {
      return new CpkgDownloadBatchTask($app['integration_config'], $app['cpkg.batch_task_translator']);
    });
  }

  public function boot(Application $app) { }
}
