<?php


namespace Curator\AppTargeting;




use Curator\FSAccess\FSAccessManager;
use Curator\IntegrationConfig;
use Curator\Status\StatusService;

class AppDetector {
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

  function __construct(StatusService $status, AppTargeterFactoryInterface $targeter_factory, FSAccessManager $fs_access) {
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
      $targeter_string = $this->status->getStatus()->adjoining_app_targeter;
      if ($targeter_string) {
        // This may be a registered service id, or the name of a callable we can invoke to get
        // a custom AppTargeter from the integration.
        $matches = [];
        $is_service_id = preg_match('/^service:([^:].*)$/', $targeter_string,$matches);
        if ($is_service_id) {
          $targeter_service_name = $matches[1];
          $this->targeter = $this->targeter_factory->getAppTargeterById($targeter_service_name);
        } else {
          if (is_callable($targeter_string)) {
            $this->targeter = call_user_func($targeter_string); // TODO: pass useful parameters
          } else {
            throw new \RuntimeException("Factory for application targeter, \"$targeter_string\", is not a callable function.");
          }
        }
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
