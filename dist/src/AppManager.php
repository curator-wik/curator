<?php
namespace Curator;


/**
 * Class AppManager
 * Helps app integration scripts and web/index.php to decide whether to
 * pull in and run more of Curator. Also abstracts Silex away from the
 * integration script, so a theoretical complete framework change could be done
 * without impacting integration scripts.
 *
 * When Curator is embedded with an application, the AppManager is provided to
 * the integration script as the return value of the phar's include. The
 * integration script may then proceed to run() Curator if it determines the
 * adjoining app has been configured to allow Curator's operation.
 *
 * This class and a handful of others (see integration_include.php) are the only
 * parts of Curator that are seen by the interpreter at all unless run() is
 * called. Composer's autoloader isn't even registered until this point, so if
 * you want to include the phar in an integration script and then decide not
 * to run the main application, vulnerabilities in the greater Curator codebase
 * would still be effectively mitigated.
 *
 * @package Curator
 */
class AppManager {
  /**
   * @var bool $isPhar
   */
  protected $isPhar;

  /**
   * @var string $curator_filename
   */
  protected $curator_filename;

  /**
   * @var \Silex\Application $silexApp
   */
  protected $silexApp;

  /**
   * @var IntegrationConfig $integration_configuration
   *   Configuration parameters provided by the application integration script.
   */
  protected $integration_configuration;

  /**
   * @var int $runMode
   *   Whether we are running standalone or embedded with an integrated app.
   */
  private $runMode;

  const RUNMODE_UNSET = 0;
  const RUNMODE_STANDALONE = 1;
  const RUNMODE_EMBEDDED   = 2;


  /**
   * AppManager constructor.
   *   Should be used only by self::create(), and by tests.
   *
   * @param $runmode
   */
  public function __construct($runmode) {
    // Until a phar stub tells us otherwise, we're not one.
    $this->isPhar = FALSE;
    $this->runMode = $runmode;
    $this->hasRun = FALSE;
    $this->silexApp = NULL;
    $this->integration_configuration = NULL;
  }

  public static function create() {
    return new static(self::RUNMODE_UNSET);
  }

  public function setIsPhar() {
    $this->isPhar = TRUE;
  }

  /**
   * @return bool
   *   TRUE if Curator is running within a phar archive, FALSE otherwise.
   */
  public function isPhar() {
    return $this->isPhar;
  }

  /**
   * Determines whether this is a standalone or embedded Curator, and records
   * the path to the Curator source code on disk, in case it needs to be used
   * as the fallback site root.
   *
   * Called exactly once, typically by the phar stub or alternatively by
   * index.php when running from unarchived source tree.
   *
   * @param string $filename
   *   The name of the file calling this function.
   *   A .phar or .php.
   * @param string $default_site_root
   *   The path to use as the fallback site root should no IntegrationConfig
   *   be provided. Should be the directory curator itself lives at.
   */
  public function determineRunMode($curator_filename) {
    $filename = basename($curator_filename);
    $curator_directory = dirname($curator_filename);
    if ($this->runMode !== self::RUNMODE_UNSET) {
      throw new \LogicException('Run mode already determined, cannot be changed.');
    }
    if (! is_string($curator_filename)) {
      throw new \InvalidArgumentException('AppManager::determineRunMode expects a string, got ' . gettype($filename));
    }
    $this->curator_filename = $curator_filename;

    if (strtolower($filename) === 'curator.phar'
      || strtolower($filename) === 'curator.php') {
      $this->runMode = self::RUNMODE_STANDALONE;
    } else {
      $this->runMode = self::RUNMODE_EMBEDDED;
    }

    $default_site_root = (array_key_exists('DOCUMENT_ROOT', $_SERVER) && !empty($_SERVER['DOCUMENT_ROOT'])) ?
      $_SERVER['DOCUMENT_ROOT']
      : $curator_directory;
    // Record fallback site root in "null" configuration.
    IntegrationConfig::getNullConfig()->setSiteRootPath($default_site_root);
  }

  public function getRunMode() {
    return $this->runMode;
  }

  public function applyIntegrationConfig(IntegrationConfig $integration_configuration) {
    $this->integration_configuration = $integration_configuration;
  }

  public function getConfiguration() {
    return empty($this->integration_configuration)
      ? IntegrationConfig::getNullConfig()
      : $this->integration_configuration;
  }

  public function run() {
    if ($this->runMode === self::RUNMODE_UNSET) {
      throw new \LogicException('Must call determineRunMode() before run().');
    }

    if (php_sapi_name() == 'cli' && ! getenv('PHPUNIT-TEST')) {
      fwrite(STDERR, "Curator cannot be used from the command line.\n");
      exit(1);
    }

    if (!defined('PHP_VERSION_ID') || PHP_MAJOR_VERSION < 5 || (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 4)) {
      die("Curator requires PHP 5.4+\n");
    }

    $this->silexApp = $this->createApplication();

    // Honor the requested task if the integration script provided one.
    if (
      $this->runMode === self::RUNMODE_EMBEDDED
      && $this->integration_configuration->getTask()
    ) {
      $task = $this->integration_configuration->getTask();
      if ($task->getDecoderServiceName() !== NULL) {
        // Perform any processing, such as queueing of a new batch job, before
        // redirecting to the route provided by the task.
        $setup_service = $this->silexApp[$task->getDecoderServiceName()];
        if ($setup_service instanceof \Curator\Task\TaskDecoderInterface) {
          $decoder_return = $setup_service->decodeTask($task);
        } else {
          throw new \LogicException(sprintf('The integration task identified decoder service "%s", but it does not implement TaskDecoderInterface.', $task->getDecoderServiceName()));
        }
      }

      // If the decoder service returned a non-null value, it is assumed that
      // the meaningful work done during this request was performed by the
      // decoder service itself. Make the result available to the integration
      // script. This feature is used during HMAC shared secret initialization.
      if ($decoder_return !== NULL) {
        return $decoder_return;
      } else {
        // Otherwise, assume current URL as basis for redirect, because
        // integration tasks ought to only be present when the integration
        // script itself is the URL.
        $is_https = isset($_SERVER['HTTPS'])
          && strtolower($_SERVER['HTTPS']) == 'on';
        $http_protocol = $is_https ? 'https://' : 'http://';
        $entrypoint_script_url = $http_protocol . $_SERVER['HTTP_HOST'];
        $entrypoint_script_url .= $_SERVER['SCRIPT_NAME'];
      }

      header('Location: ' . $entrypoint_script_url . $task->getRoute());
      header('Cache-Control: no-cache'); // Not a permanent redirect.
    } else {
      if ($this->hasRun()) {
        throw new \LogicException('Curator has already been run().');
      }
      
      $this->silexApp->run();
    }
  }

  /**
   * @return \Curator\CuratorApplication
   */
  public function createApplication() {
    if ($this->isPhar()) {
      set_include_path('phar://curator.phar');
      require __DIR__.'/../vendor/autoload.php';
    } else {
      require __DIR__.'/../../vendor/autoload.php';
    }


    // Engage conversion of errors to ErrorExceptions.
    \Symfony\Component\Debug\ErrorHandler::register();

    $app = new CuratorApplication($this, $this->curator_filename);

    // For now, nobody's running this outside a phar that isn't a developer
    if (! $this->isPhar()) {
      $app['debug'] = TRUE;
    }
    return $app;
  }

  public function hasRun() {
    return $this->silexApp !== NULL;
  }

}
