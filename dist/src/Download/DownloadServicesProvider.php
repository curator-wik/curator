<?php


namespace Curator\Download;


use Curator\CuratorApplication;
use Silex\Application;
use Silex\ServiceProviderInterface;

class DownloadServicesProvider implements ServiceProviderInterface {
  public function register(Application $app) {
    $app['download.curl_download_task'] = $app->share(function($app) {
      /**
       * @var CuratorApplication $app
       */
      return new CurlDownloadBatchTask($app['integration_config']);
    });
  }

  public function boot(Application $app) { }
}
