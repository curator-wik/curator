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
  protected $siteRootPath;

  protected $appVersion;

  protected $siteName;

  /**
   * @var string $defaultTimeZone
   */
  protected $defaultTimeZone;

  public function __construct() {
    $this->taskBuilder = NULL;
  }

  public function setSiteRootPath($site_root_path) {
    $this->siteRootPath = $site_root_path;
  }

  public function getSiteRootPath() {
    return $this->siteRootPath;
  }

  public function setDefaultTimezone($tz_string) {
    $this->defaultTimeZone = $tz_string;
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

  public function setCustomAppTargeter(TargeterInterface $targeter) {

  }

  public static function getNullConfig() {
    if (IntegrationConfig::$nullConfig == NULL) {
      IntegrationConfig::$nullConfig = new IntegrationConfig();
    }

    return IntegrationConfig::$nullConfig;
  }
}
