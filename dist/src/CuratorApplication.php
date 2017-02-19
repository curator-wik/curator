<?php


namespace Curator;

use Curator\Authorization\InstallationAge;
use Curator\Batch\RunnerService;
use Curator\Controller\StaticContentController;
use Curator\FSAccess\DefaultFtpConfigurationProvider;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
use Curator\Persistence\FilePersistence;
use Curator\Status\StatusService;
use Curator\Authorization\AuthorizationMiddleware;
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
   * @param AppManager $app_manager
   * @param string $curator_filename
   *   The path to the file containing the first line of curator PHP code.
   *   Capture the value of __FILE__ and pass it along. It's
   *   generally along the lines of /something/curator.phar.
   */
  public function __construct(AppManager $app_manager, $curator_filename) {
    parent::__construct();
    $this->integrationConfig = $app_manager->getConfiguration();
    $this->curator_filename = $curator_filename;
    $this->configureDefaultTimezone();
    $this->register(new \Silex\Provider\SessionServiceProvider(), ['session.test' => getenv('PHPUNIT-TEST') === '1']);
    $this->register(new \Silex\Provider\ServiceControllerServiceProvider());
    $this->register(new \Silex\Provider\TranslationServiceProvider(), array(
      'locale_fallbacks' => array('en'),
    ));

    $this['app_manager'] = $this->share(function() use($app_manager) {
      return $app_manager;
    });
    $this->defineServices();
    $this->prepareRoutes();

    /**
     * When no other route matches, see if it is a file under web/curator-gui
     */
    $this->error(function(NotFoundHttpException $e) {
      /**
       * @var Request $request
       */
      $request = $this['request_stack']->getCurrentRequest();
      $file_response = $this['static_content_controller']->serveStaticFile($request);
      if ($file_response) {
        return $file_response;
      } else {
        return NULL;
      }
    }, -4);
  }

  protected function configureDefaultTimezone() {
    if (
      ! is_string($this->integrationConfig->getDefaultTimeZone())
      || ! date_default_timezone_set($this->integrationConfig->getDefaultTimezone())
    ) {
      date_default_timezone_set('UTC');
    }
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
    $this->get('/', 'static_content_controller:generateSinglePageHost');
    $this->mount('/api/v1', new Provider\APIv1\UnauthenticatedEndpointsProvider());
    $this->mount('/api/v1', new Provider\APIv1\AuthenticatedOrUnconfiguredEndpointsProvider());
  }

  protected function defineServices() {
    $this['static_content_controller'] = $this->share(function($app) {
      return new StaticContentController($app['app_manager']);
    });

    $this['authorization.middleware'] = $this->share(function($app) {
      return new AuthorizationMiddleware($app['session'], $app['status'], $app['persistence'], $app['app_manager'], $app['authorization.installation_age'], $this->getCuratorFilename());
    });
    $this['authorization.installation_age'] = function() {
      return new InstallationAge();
    };

    $this['fs_access'] = $this->share(function($app) {
      $manager = new FSAccessManager($app['fs_access.read_adapter'], $app['fs_access.write_adapter']);
      $manager->setWorkingPath($this->integrationConfig->getSiteRootPath());
      return $manager;
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

    $this['status'] = $this->share(function($app) {
      return new StatusService($app['persistence'], $app['session']);
    });

    $this['batch.runner_service'] = function($app) {
      return new RunnerService($app['persistence'], $app['status']);
    };
  }
}
