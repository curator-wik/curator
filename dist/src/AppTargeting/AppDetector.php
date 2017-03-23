<?php


namespace Curator\AppTargeting;




use Curator\FSAccess\FSAccessManager;
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
   * @var AppTargeterFactoryInterface $targeter_factory
   */
  protected $targeter_factory;

  /**
   * @var TargeterInterface $targeter
   */
  protected $targeter = NULL;

  function __construct(IntegrationConfig $curator_config, StatusService $status, AppTargeterFactoryInterface $targeter_factory, FSAccessManager $fs_access) {
    $this->curatorConfig = $curator_config;
    $this->status = $status;
    $this->targeter_factory = $targeter_factory;
    $this->fs_access = $fs_access;
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
        $this->targeter = $this->targeter_factory->getAppTargeterById($targeter_service_name);
      } else {
        $id = $this->detectAdjoiningApp();
        if ($id !== NULL) {
          $this->targeter = $this->targeter_factory->getAppTargeterById($id);
        }
      }
    }

    return $this->targeter;
  }

  /**
   * @return string|null
   *   The service ID for the app targeter appropriate for the adjoining app.
   */
  public function detectAdjoiningApp() {
    $app_signatures = [
      'drupal7' => ['includes/bootstrap.inc', 'modules/system/system.info']
    ];

    foreach ($app_signatures as $target_service_id => $app_signature) {
      $match = TRUE;
      foreach ($app_signature as $filename) {
        if (! $this->fs_access->isFile($filename)) {
          $match = FALSE;
          break;
        }
      }
      if ($match) {
        return $target_service_id;
      }
    }
    // TODO: We probably want to have a generic targeter someday.
    return NULL;
  }
}
