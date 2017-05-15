<?php


namespace Curator;

use Curator\AppTargeting\AppTargeterFactoryInterface;
use Curator\AppTargeting\AppTargetingProvider;
use Curator\Authorization\InstallationAge;
use Curator\Batch\RunnerService;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Controller\StaticContentController;
use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Cpkg\CpkgServicesProvider;
use Curator\FSAccess\DefaultFtpConfigurationProvider;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
use Curator\Persistence\FilePersistence;
use Curator\Status\StatusModel;
use Curator\Status\StatusService;
use Curator\Authorization\AuthorizationMiddleware;
use Curator\Task\Decoder\InitializeHmacSecret;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CuratorApplication extends Application implements AppTargeterFactoryInterface {

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
    $this['integration_config'] = $app_manager->getConfiguration();
    $this->curator_filename = $curator_filename;
    $this->configureDefaultTimezone();
    $this->register(new \Silex\Provider\SessionServiceProvider(), ['session.test' => getenv('PHPUNIT-TEST') === '1']);
    $this->register(new \Silex\Provider\ServiceControllerServiceProvider());
    $this->register(new AppTargetingProvider());
    $this->register(new CpkgServicesProvider());
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
      ! is_string($this['integration_config']->getDefaultTimeZone())
      || ! date_default_timezone_set($this['integration_config']->getDefaultTimezone())
    ) {
      date_default_timezone_set('UTC');
    }
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

    $this['task.decoder.initialize_hmac_secret'] = $this->share(function($app) {
      return new InitializeHmacSecret();
    });

    $this['fs_access'] = $this->share(function($app) {
      $manager = new FSAccessManager($app['fs_access.read_adapter'], $app['fs_access.write_adapter']);
      $manager->setWorkingPath($this['integration_config']->getSiteRootPath());
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
      $key = 'persistence:' . $this['integration_config']->getSiteRootPath();
      return new Util\Flock($key);
    });

    $this['persistence.file'] = $this->share(function($app) {
      /**
       * @var CuratorApplication $app
       */
      $safe_extension = pathinfo($this->getCuratorFilename(), PATHINFO_EXTENSION);
      return new FilePersistence($app['fs_access'], $app['persistence.lock'], $app['integration_config'], $safe_extension);
    });
    $this['persistence'] = $this->raw('persistence.file');

    $this['status'] = $this->share(function($app) {
      return new StatusService($app['persistence'], $app['session']);
    });

    $this['batch.runner_service'] = function($app) {
      return new RunnerService($app['persistence'], $app['status']);
    };

    $this['batch.task_scheduler'] = $this->share(function($app) {
      return new TaskScheduler($app['persistence'], $app['session']);
    });

    $this['batch.taskgroup_manager'] = $this->share(function($app) {
      return new TaskGroupManager($app['persistence'], $app['batch.task_scheduler']);
    });

  }

  /**
   * @inheritdoc
   */
  public function getAppTargeterById($app_id) {
    return $this["app_targeting.$app_id"];
  }
}
