<?php


namespace Curator\Tests\Functional;


use Curator\AppManager;

/**
 * Class FunctionalTestAppManager
 *
 * Injects test services.
 */
class FunctionalTestAppManager extends AppManager
{
  protected $serviceOverrides;

  public function __construct($runMode, $serviceOverrides)
  {
    $this->serviceOverrides = $serviceOverrides;
    parent::__construct($runMode);
  }

  public function getServiceOverride($id, $app) {
    if (! empty($this->serviceOverrides[$id])) {
      $override = $this->serviceOverrides[$id];
      if (is_string($override)) {
        return $app->raw($override);
      } else {
        list($factory, $share) = $this->serviceOverrides[$id];
        if ($share) {
          return $app->share($factory);
        } else {
          return $factory;
        }
      }
    } else {
      return null;
    }
  }

  public function getApplication() {
    return $this->silexApp;
  }
}
