<?php


namespace Curator\Status;

use Curator\Persistence\PersistenceInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class StatusService
 *   Determines the overall status of the application and of the connected user.
 *
 *   This includes whether we have all the information needed to write to the
 *   filesystem, where is the root of the managed web application is, and if the
 *   connected user is authorized.
 */
class StatusService {

  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var SessionInterface $session
   */
  protected $session;

  /**
   * @var StatusModel $model
   */
  protected $model;

  function __construct(PersistenceInterface $persistence, SessionInterface $user_session) {
    $this->persistence = $persistence;
    $this->session = $user_session;
    $this->model = null;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $user_session
   * @return StatusModel
   */
  public function getStatus() {
    if ($this->model === null) {
      $this->reloadStatus();
    }
    return $this->model;
  }

  public function reloadStatus() {
    $result = new StatusModel();
    $this->persistence->beginReadOnly();

    if ($this->persistence->get('site_root') && $this->persistence->get('write_config')) {
      $result->is_configured = TRUE;
    }

    $result->alarm_signal_works = $this->persistence->get('alarm_signal_works', FALSE);
    $result->flush_works = $this->persistence->get('flush_works', FALSE);
    $result->adjoining_app_targeter = $this->persistence->get('adjoining_app_targeter');
    $result->write_working_path = $this->persistence->get('write_working_path');
    $this->persistence->end();

    $result->is_authenticated = $this->session->get('IsAuthenticated', FALSE);

    $this->model = $result;
  }
}
