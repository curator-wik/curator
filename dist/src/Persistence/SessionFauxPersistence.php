<?php


namespace Curator\Persistence;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class SessionFauxPersistence
 *   This class is registered in the DI container as the persistence handler
 *   only during one-time setup of the application, before a real persistence
 *   mechanism has been established.
 *
 *   The one-time setup process copies values written to the session to the
 *   real persistence store once it is available.
 *
 *   This class does not fully implement PersistenceInterface, which is a really
 *   quite ugly actuality. Methods pertaining to concurrent access protection
 *   are no-ops because the default PHP session handler implements a full mutex
 *   whenever a script holds the session open, and adding additional locking on
 *   top of that seems redundant at best. We don't exclusively control when
 *   it is time to release the session-based mutex, so end() is left empty.
 *
 *   In fact, this class isn't employed during times of concurrent requests in
 *   the same session anyway.
 */
class SessionFauxPersistence implements PersistenceInterface {

  /**
   * @var SessionInterface $session
   */
  protected $session;

  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  public function set($key, $value) {
    if ($value === NULL) {
      $this->session->remove("faux_persistence.$key");
    } else {
      $this->session->set("faux_persistence.$key", $value);
    }
  }

  public function get($key, $defaultValue = NULL) {
    return $this->session->get("faux_persistence.$key", $defaultValue);
  }

  /**
   * @return array All persisted values.
   */
  public function getAll() {
    $prefix = 'faux_persistence.';
    $result = [];
    foreach ($this->session->all() as $key => $value) {
      if(strncmp($key, $prefix, strlen($prefix)) === 0) {
        $result[substr($key, strlen($prefix))] = $value;
      }
    }
    return $result;
  }

  public function beginReadOnly() { }

  public function beginReadWrite() { }

  public function end() { }

  public function popEnd() { }

}