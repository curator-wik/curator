<?php


namespace Curator;

use Curator\AppTargeting\AppTargeterFactoryInterface;
use Curator\AppTargeting\AppTargetingProvider;
use Curator\Authorization\InstallationAge;
use Curator\Batch\RunnerService;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Controller\StaticContentController;
use Curator\Cpkg\CpkgServicesProvider;
use Curator\Download\DownloadServicesProvider;
use Curator\FSAccess\DefaultFtpConfigurationProvider;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
use Curator\Persistence\FilePersistence;
use Curator\Persistence\SessionFauxPersistence;
use Curator\Status\StatusService;
use Curator\Authorization\AuthorizationMiddleware;
use Curator\Task\Decoder\TaskDecoderServicesProvider;
use Curator\Util\SessionPrepService;
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

    $this->curator_filename = $curator_filename;
    $this->register(new \Silex\Provider\SessionServiceProvider(), [
      'session.storage.options' => [
        'name' => 'CURATOR_' . substr(md5($this->curator_filename), 0, 8),
        'cookie_httponly' => true,
      ],
      'session.test' => getenv('PHPUNIT-TEST') === '1' && php_sapi_name() == 'cli']
    );

    $this->register(new \Silex\Provider\ServiceControllerServiceProvider());
    $this->register(new AppTargetingProvider());
    $this->register(new CpkgServicesProvider());
    $this->register(new DownloadServicesProvider());
    $this->register(new TaskDecoderServicesProvider());
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

  public function setIntegrationConfig(IntegrationConfig $config) {
    if ($this->isIntegrationConfigSet()) {
      throw new \LogicException('Integration config has already been set.');
    }

    $this['integration_config'] = $config;
    $this->configureDefaultTimezone();
  }

  public function isIntegrationConfigSet() {
    return array_key_exists('integration_config', $this);
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
    $this->get('/batch-client_alpha.js', function(CuratorApplication $app) {
      /**
       * @var StaticContentController $static_controller
       */
      $static_controller = $app['static_content_controller'];
      return $static_controller->serveStaticFileAtPath($static_controller->getWebPath() . "batch-client_alpha.js");
    });
    $this->get('/jquery-2.2.4.min.js', function(CuratorApplication $app) {
      /**
       * @var StaticContentController $static_controller
       */
      $static_controller = $app['static_content_controller'];
      return $static_controller->serveStaticFileAtPath($static_controller->getWebPath() . "jquery-2.2.4.min.js");
    });
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
      $manager->setWorkingPath($this['integration_config']->getSiteRootPath());
      // TODO: create configuration system that allows selection of other write adapters and uses autodetectWriteWorkingPath()
      $manager->setWriteWorkingPath($this['integration_config']->getSiteRootPath());
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
    $this['persistence.session_faux'] = $this->share(function($app) {
      return new SessionFauxPersistence($app['session']);
    });
    $this['persistence'] = $this->share(function($app) {
      // Use persistence.file unless it has not yet been configured.
      // TODO: is there a way to avoid a lock/read/unlock for every container
      // instantiation? Session not guaranteed to be startable yet :(.
      // Maybe a persistence proxy than can switcharoo after kernel is booted?
      /**
       * @var FilePersistence $fp
       */
      $fp = $app['persistence.file'];
      $fp->beginReadOnly();
      $hasWriteConfig = $fp->get('write_config', NULL);
      $fp->end();

      if ($hasWriteConfig !== NULL) {
        $sp = 'persistence.file';
      } else {
        $sp = 'persistence.session_faux';
      }

      return $app[$sp];
    });

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

    $this['session.prep'] = $this->share(function($app) {
      return new SessionPrepService();
    });
  }

  /**
   * @inheritdoc
   */
  public function getAppTargeterById($app_id) {
    return $this["app_targeting.$app_id"];
  }
}
