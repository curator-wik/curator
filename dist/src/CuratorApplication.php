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
use Curator\FSAccess\FSAccessInterface;
use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\StreamWrapperFileAdapter;
use Curator\FSAccess\StreamWrapperFtpAdapter;
use Curator\Persistence\FilePersistence;
use Curator\Persistence\PersistenceInterface;
use Curator\Persistence\SessionFauxPersistence;
use Curator\Status\StatusModel;
use Curator\Status\StatusService;
use Curator\Authorization\AuthorizationMiddleware;
use Curator\Task\Decoder\TaskDecoderServicesProvider;
use Curator\Tests\Functional\FunctionalTestAppManager;
use Curator\Util\SessionPrepService;
use Silex\Application;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CuratorApplication extends Application implements AppTargeterFactoryInterface {

  /**
   * @var string $curator_filename
   *   The path to the file containing the first line of curator PHP code.
   */
  protected $curator_filename;

  /**
   * @var AppManager $app_manager
   */
  protected $app_manager;

  /**
   * CuratorApplication constructor.
   *
   * @param AppManager $app_manager
   * @param string $curator_filename
   *   The path to the file containing the first line of curator PHP code.
   *   Capture the value of __FILE__ and pass it along. It's
   *   generally along the lines of /something/curator.phar.
   */
  public function __construct(AppManager $app_manager, $curator_filename, ErrorHandler $errorHandler) {
    $this->app_manager = $app_manager;
    parent::__construct();

    $this->curator_filename = $curator_filename;
    $this['error_handler'] = $errorHandler;
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

    $this['integration_config'] = null; // Unless/until set by setIntegrationConfig().
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

    // With the benefit of request cookies, set site root if we don't already have it.
    $this->before(function(Request $request, CuratorApplication $app) {
      /** @var FSAccessInterface $fs_access */
      $fs_access = $app['fs_access'];
      if (! $fs_access->isWorkingPathSet()) {
        $site_root = $app['status']->getStatus()->site_root;
        if (empty($site_root)) {
          $app['status']->reloadStatus();
          $site_root = $app['status']->getStatus()->site_root;
        }

        $app['fs_access']->setWorkingPath($site_root);
        // TODO: Whole configuration layer that looks at persistence and sets write path better,
        // or does not do it if not in persistence, reports via /status, and expects client to fix.
        $app['fs_access']->setWriteWorkingPath($site_root);
      }

      // Pull in timezone because symfony is much happier this way
      /** @var StatusModel $status */
      $status = $app['status']->getStatus();
      if (! date_default_timezone_set($status->timezone)) {
        date_default_timezone_set('UTC');
      }
    });
  }

  public function setIntegrationConfig(IntegrationConfig $config) {
    if ($this->isIntegrationConfigSet()) {
      throw new \LogicException('Integration config has already been set.');
    }

    $this['integration_config'] = $config;

    // Update things persisted in the StatusModel with any new values we've received.
    /**
     * @var \Curator\Status\StatusModel $config
     */
    $config = $this['status']->getStatus();
    $changes = [];

    $site_root = $this['integration_config']->getSiteRootPath();
    if ($config->site_root != $site_root) {
      $changes['site_root'] = $site_root;
    }

    $targeter = $this['integration_config']->getCustomAppTargeter();
    if ($config->adjoining_app_targeter != $targeter) {
      $changes['adjoining_app_targeter'] = $targeter;
    }

    $tz = $this['integration_config']->getDefaultTimezone();
    if ($config->timezone != $tz && !empty($tz)) {
      $changes['timezone'] = $tz;
    }

    if (count($changes)) {
      $persistence = $this['persistence'];
      /**
       * @var PersistenceInterface $persistence
       */
      $persistence->beginReadWrite();
      foreach ($changes as $key => $value) {
        $persistence->set($key, $value);
      }
      $persistence->popEnd();

      $this['status']->reloadStatus();
    }
  }

  public function isIntegrationConfigSet() {
    return $this['integration_config'] !== null;
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

    // Main fs_access service, may use ftp etc. for writes.
    $this['fs_access'] = $this->share(function($app) {
      $manager = new FSAccessManager($app['fs_access.read_adapter'], $app['fs_access.write_adapter']);
      return $manager;
    });

    // Mounted fs_access service, always uses mounted filesystem for writes.
    $this['fs_access.mounted'] = $this->share(function ($app) {
      $manager = new FSAccessManager($app['fs_access.read_adapter.filesystem'], $app['fs_access.write_adapter.filesystem']);
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
      $key = 'persistence:' . $this->curator_filename;
      return new Util\Flock($key);
    });

    $this['persistence.file'] = $this->share(function($app) {
      /**
       * @var CuratorApplication $app
       */
      $safe_extension = pathinfo($this->getCuratorFilename(), PATHINFO_EXTENSION);
      return new FilePersistence($app['fs_access'], $app['fs_access.read_adapter'], $app['persistence.lock'], dirname($this->getCuratorFilename()), $safe_extension);
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
   * Override's Pimple's ArrayAccess implementation for settting services.
   *
   * This gives special App Managers used in tests an opportunity to provide alternative
   * service implementations.
   * @see FunctionalTestAppManager
   *
   * @param string $id
   * @param mixed $value
   */
  public function offsetSet($id, $value) {
    $override = $this->app_manager->getServiceOverride($id, $this);
    if ($override !== null) {
      $this->values[$id] = $override;
    } else {
      $this->values[$id] = $value;
    }
  }

  /**
   * @inheritdoc
   */
  public function getAppTargeterById($app_id) {
    return $this["app_targeting.$app_id"];
  }
}
