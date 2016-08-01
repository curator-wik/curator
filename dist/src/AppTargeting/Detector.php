<?php


namespace Curator\AppTargeting;


use Curator\IntegrationConfigInterface;

class Detector {
  /**
   * @var IntegrationConfigInterface $curatorConfig
   */
  protected $curatorConfig;

  /**
   * @var String $request_path
   *   The path found in the HTTP request that invoked Curator.
   */
  protected $request_path;

  function __construct(IntegrationConfigInterface $wit_config, $request_path) {
    $this->curatorConfig = $wit_config;
    $this->request_path = $request_path;
  }

  /**
   * @return string
   *   The name of the adjoining application.
   *   Must match a service app_targeting.app.*.config
   */
  function detectAdjoiningApp() {
    // If the hard config tells us the app we're adjoining, we're done.
    $hc_app = $this->curatorConfig['app'];
    if (!empty($hc_app)) {
      return $hc_app;
    }


  }
}
