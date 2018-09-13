<?php


namespace Curator;


use Curator\AppTargeting\TargeterInterface;
use Curator\Task\TaskInterface;

class IntegrationConfig {

  /**
   * @var IntegrationConfig $nullConfig
   *   A canonical instance for the expression of no configuration.
   */
  protected static $nullConfig;

  /**
   * @var TaskBuilder $taskBuilder
   *   Describes the action the adjoining app needs curator to perform.
   */
  protected $taskBuilder;

  /**
   * @var string $siteRootPath
   *   The path to the root of the site on the host filesystem or custom
   *   filesystem read adapter.
   */
  protected $siteRootPath = '';

  protected $rollbackCapturePath = '';

  protected $appVersion;

  protected $siteName;

  /**
   * @var bool $preauthenticated
   *   Boolean indicating whether Curator should regard the user as preauthenticated.
   */
  protected $preauthenticated;

  /**
   * @var string|null $targeter
   */
  protected $targeter;

  /**
   * @var string $defaultTimeZone
   */
  protected $defaultTimeZone;

  public function __construct() {
    $this->taskBuilder = NULL;
    $this->preauthenticated = FALSE;
  }

  /**
   * Allows the adjoining application to indicate that the user is authorized to run Curator.
   *
   * Currently, it is necessary for the adjoining application to call this method on the
   * IntegrationConfig it passes to applyIntegrationConfig in order for the resulting session to be useful.
   *
   * @param bool $preauthenticated_stance
   * @return $this
   */
  public function setPreauthenticated($preauthenticated_stance = TRUE) {
    $this->preauthenticated = (bool)$preauthenticated_stance;
    return $this;
  }

  public function isPreauthenticated() {
    return $this->preauthenticated;
  }

  public function setSiteRootPath($site_root_path) {
    $this->siteRootPath = $site_root_path;
    return $this;
  }

  public function getSiteRootPath() {
    return $this->siteRootPath;
  }

  public function setRollbackCapturePath($rollback_capture_path) {
    $this->rollbackCapturePath = $rollback_capture_path;
    return $this;
  }

  public function getRollbackCapturePath() {
    return $this->rollbackCapturePath;
  }

  public function setDefaultTimezone($tz_string) {
    $this->defaultTimeZone = $tz_string;
    return $this;
  }

  public function getDefaultTimezone() {
    return $this->defaultTimeZone;
  }

  /**
   * @return TaskInterface
   */
  public function getTask() {
    if ($this->taskBuilder == NULL) {
      return NULL;
    } else {
      return $this->taskBuilder->getTask();
    }
  }

  /**
   * @return TaskBuilder
   */
  public function taskIs() {
    $this->taskBuilder = new TaskBuilder();
    return $this->taskBuilder;
  }

  /**
   * Sets the name of a callable factory that should return your custom implementation
   * of \Curator\AppTargeting\TargeterInterface.
   *
   * @param string $targeter
   *   The name of a function or static method that you have made available to the interpreter
   *   at all times Curator is running, which should return an instantiated implementation of
   *   \Curator\AppTargeting\TargeterInterface.
   *
   *   Special values beginning with "service:" are interpreted as a service id of an
   *   app targeter that ships with the Curator .phar and is registered in the DI container.
   *
   * @return $this
   */
  public function setCustomAppTargeter($targeter) {
    $this->targeter = $targeter;
    return $this;
  }

  public function getCustomAppTargeter() {
    return $this->targeter;
  }

  public static function getNullConfig() {
    if (IntegrationConfig::$nullConfig == NULL) {
      IntegrationConfig::$nullConfig = new IntegrationConfig();
    }

    return IntegrationConfig::$nullConfig;
  }
}
