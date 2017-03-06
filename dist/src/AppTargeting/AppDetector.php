<?php


namespace Curator\AppTargeting;




use Curator\IntegrationConfig;
use Curator\Status\StatusService;

class AppDetector {
  /**
   * @var IntegrationConfig $curatorConfig
   */
  protected $curatorConfig;

  /**
   * @var StatusService $status
   */
  protected $status;

  /**
   * @var String $request_path
   *   The path found in the HTTP request that invoked Curator.
   */
  protected $request_path;

  /**
   * @var TargeterInterface $targeter
   */
  protected $targeter = NULL;

  /**
   * @var \Pimple $di_container
   *   Dependency injection container, for getTargeter() to access service names
   *   given in variables.
   */
  protected $di_container;

  function __construct(IntegrationConfig $curator_config, StatusService $status, \Pimple $di_container, $request_path) {
    $this->curatorConfig = $curator_config;
    $this->status = $status;
    $this->di_container = $di_container;
    $this->request_path = $request_path;
  }

  /**
   * Gets the Application Targeter for the adjoining application, if known.
   *
   * @return TargeterInterface|null
   */
  function getTargeter() {
    if ($this->targeter == NULL) {
      if ($this->curatorConfig->getCustomAppTargeter()) {
        $this->targeter = $this->curatorConfig->getCustomAppTargeter();
      } else if ($this->status->getStatus()->adjoining_app_targeter) {
        $targeter_service_name = $this->status->getStatus()->adjoining_app_targeter;
        $this->targeter = $this->di_container["app_targeting.$targeter_service_name"];
      }
    }

    return $this->targeter;
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
