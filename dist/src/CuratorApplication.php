<?php


namespace Curator;

use Curator\Controller\SinglePageHostController;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
use Curator\FSAccess\TempFtpConfigurationProvider;
use Curator\Persistence\FilePersistence;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CuratorApplication extends Application {

  /**
   * @var IntegrationConfig $integrationConfig
   *   Configuration from an application integration script, if present.
   */
  protected $integrationConfig;

  public function __construct(IntegrationConfig $integration_config) {
    parent::__construct();
    $this->integrationConfig = $integration_config;

    // TODO: do we need this? It's in parameter syntax which is weird: $this['app_manager'] = $app_manager;

    $this->defineServices();
    $this->prepareRoutes();

    /**
     * When no other route matches, see if it is a file under web/curator-gui
     */
    $this->error(function(NotFoundHttpException $e, Request $request) {
      $file_response = SinglePageHostController::serveStaticFile($request);
      if ($file_response) {
        return $file_response;
      } else {
        return NULL;
      }
    }, -4);
  }

  protected function prepareRoutes() {
    $this->get('/', '\Curator\Controller\SinglePageHostController::generateSinglePageHost');
  }

  protected function defineServices() {
    $this['fs_access'] = $this->share(function($app) {
      return new FSAccessManager($app['fs_access.read_adapter'], $app['fs_access.write_adapter']);
    });

    $this['fs_access.path_parser.system'] = $this->share(function() {
      if (defined('PHP_WINDOWS_VERSION_MAJOR') && PHP_WINDOWS_VERSION_MAJOR) {
        return new WindowsPathParser();
      } else {
        return new PosixPathParser();
      }
    });

    $this['fs_access.ftp_config'] = $this->share(function($app) {
      return new TempFtpConfigurationProvider();
    });

    $this['fs_access.write_adapter.ftp'] = $this->share(function($app) {
      return new StreamWrapperFtpAdapter($app['fs_access.ftp_config']);
    });

    // The StreamWrapperFtpAdapter provides both read and write services
    $this['fs_access.read_adapter.ftp'] = $this['fs_access.write_adapter.ftp'];

    $this['fs_access.read_adapter.filesystem']
      = $this['fs_access.write_adapter.filesystem']
      = $this->share(function($app) {
        return new StreamWrapperFileAdapter($app['fs_access.path_parser.system']);
    });

    $this['fs_access.read_adapter'] = $this['fs_access.read_adapter.filesystem'];
    $this['fs_access.write_adapter'] = $this['fs_access.write_adapter.ftp'];

    $this['persistence'] = $this->share(function($app) {
      return new FilePersistence($app['fs_access']);
    });
  }
}
