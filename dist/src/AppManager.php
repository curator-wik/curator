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
   * @var AppManager $singleton
   */
  private static $singleton = NULL;

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

  protected function __construct() {
    // Until a phar stub tells us otherwise, we're not one.
    $this->isPhar = FALSE;
    $this->runMode = self::RUNMODE_UNSET;
    $this->hasRun = FALSE;
    $this->silexApp = NULL;
    $this->integration_configuration = NULL;
  }

  public static function singleton() {
    if (AppManager::$singleton == NULL) {
      AppManager::$singleton = new static();
    }

    return AppManager::$singleton;
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
    if ($this->runMode === self::RUNMODE_UNSET) {
      throw new \LogicException('Precondition violation: getRunMode() called before determineRunMode()');
    }
    return $this->runMode;
  }

  public function applyConfiguration(IntegrationConfig $integration_configuration) {
    $this->integration_configuration = $integration_configuration;
  }

  public function getConfiguration() {
    return empty($this->integration_configuration)
      ? IntegrationConfig::getNullConfig()
      : $this->integration_configuration;
  }

  public function run() {
    if ($this->hasRun()) {
      throw new \LogicException('Curator has already been run().');
    }
    if ($this->runMode === self::RUNMODE_UNSET) {
      throw new \LogicException('Must call determineRunMode() before run().');
    }
    if ($this->isPhar()) {
      set_include_path('phar://curator.phar');
    }
    require __DIR__.'/../vendor/autoload.php';

    // Engage conversion of errors to ErrorExceptions.
    \Symfony\Component\Debug\ErrorHandler::register();


    // Engage conversion of errors to ErrorExceptions.
    \Symfony\Component\Debug\ErrorHandler::register();

    $app = new CuratorApplication($this->getConfiguration(), $this->curator_filename);

    // For now, nobody's running this outside a phar that isn't a developer
    if (! $this->isPhar()) {
      $app['debug'] = TRUE;
    }

    $this->silexApp = $app;

    $app->run();
  }

  public function hasRun() {
    return $this->silexApp !== NULL;
  }


}
