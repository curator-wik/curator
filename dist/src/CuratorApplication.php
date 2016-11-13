<?php


namespace Curator;

use Curator\Controller\SinglePageHostController;
use Curator\FSAccess\DefaultFtpConfigurationProvider;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
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

  /**
   * @var string $curator_filename
   *   The path to the file containing the first line of curator PHP code.
   */
  protected $curator_filename;

  /**
   * CuratorApplication constructor.
   *
   * @param \Curator\IntegrationConfig $integration_config
   * @param string $curator_filename
   *   The path to the file containing the first line of curator PHP code.
   *   This file captures the value of __FILE__ and passes it along. It's
   *   generally along the lines of /something/curator.phar.
   */
  public function __construct(IntegrationConfig $integration_config, $curator_filename) {
    parent::__construct();
    $this->integrationConfig = $integration_config;

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

  /**
   * @return \Curator\IntegrationConfig
   */
  public function getIntegrationConfig() {
    return $this->integrationConfig;
  }

  /**
   * The path to the file containing the first line of curator PHP code.
   *
   * @return string
   */
  public function getCuratorFilename() {
    return $this->curator_filename;
  }

  protected function prepareRoutes() {
    $this->get('/', '\Curator\Controller\SinglePageHostController::generateSinglePageHost');
  }

  protected function defineServices() {
    $this->register(new \Silex\Provider\TranslationServiceProvider(), array(
      'locale_fallbacks' => array('en'),
    ));

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
      return new DefaultFtpConfigurationProvider($app['persistence']);
    });

    $ftp_adapter = $this->share(function($app) {
      return new StreamWrapperFtpAdapter($app['fs_access.ftp_config']);
    });
    $this['fs_access.write_adapter.ftp'] = $ftp_adapter;
    $this['fs_access.read_adapter.ftp'] = $ftp_adapter;

    $this['fs_access.read_adapter.filesystem']
      = $this['fs_access.write_adapter.filesystem']
      = $this->share(function($app) {
        return new StreamWrapperFileAdapter($app['fs_access.path_parser.system']);
    });

    $this['fs_access.read_adapter'] = $this->raw('fs_access.read_adapter.filesystem');
    $this['fs_access.write_adapter'] = $this->raw('fs_access.write_adapter.filesystem');

    $this['persistence.lock'] = $this->share(function($app) {
      /**
       * @var CuratorApplication $app
       */
      $key = 'persistence:' . $app->getIntegrationConfig()->getSiteRootPath();
      return new Util\Flock($key);
    });

    $this['persistence.file'] = $this->share(function($app) {
      /**
       * @var CuratorApplication $app
       */
      $safe_extension = pathinfo($this->getCuratorFilename(), PATHINFO_EXTENSION);
      return new FilePersistence($app['fs_access'], $app['persistence.lock'], $app->getIntegrationConfig(), $safe_extension);
    });
    $this['persistence'] = $this->raw('persistence.file');
  }
}
