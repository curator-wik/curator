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

  protected $appRootPath;

  protected $appVersion;

  protected $siteName;

  public function __construct() {
    $this->taskBuilder = NULL;
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
