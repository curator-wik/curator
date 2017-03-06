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
   * @var StatusModel $model
   */
  protected $model;

  function __construct(PersistenceInterface $persistence, SessionInterface $user_session) {
    $this->persistence = $persistence;

    $this->reloadStatus($user_session);
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $user_session
   * @return StatusModel
   */
  public function getStatus() {
    return $this->model;
  }

  public function reloadStatus(SessionInterface $user_session) {
    $result = new StatusModel();
    $this->persistence->beginReadOnly();

    if ($this->persistence->get('site_root') && $this->persistence->get('write_config')) {
      $result->is_configured = TRUE;
    }

    $result->alarm_signal_works = $this->persistence->get('alarm_signal_works', FALSE);
    $result->flush_works = $this->persistence->get('flush_works', FALSE);
    $result->adjoining_app_targeter = $this->persistence->get('adjoining_app_targeter');
    $this->persistence->end();

    $this->model = $result;
  }
}
