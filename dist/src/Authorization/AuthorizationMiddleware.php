<?php


namespace Curator\Authorization;


use Curator\AppManager;
use Curator\Persistence\PersistenceInterface;
use Curator\Status\StatusService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthorizationMiddleware {

  /**
   * Indicates the difference in hours between time() and
   * max(script mtime, script ctime) that is permissible in case no
   * authentication has been configured.
   */
  const UNCONFIGURED_NEGLECT_HOURS = 4;

  /**
   * @var SessionInterface $session
   */
  protected $session;

  /**
   * @var StatusService $status
   */
  protected $status;

  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  /**
   * @var AppManager $app_manager
   */
  protected $app_manager;

  /**
   * @var InstallationAgeInterface $installation_age
   */
  protected $installation_age;

  /**
   * @var string $curator_filename
   */
  protected $curator_filename;


  /**
   * AuthorizationMiddleware constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   * @param \Curator\Status\StatusService $statusService
   * @param \Curator\Persistence\PersistenceInterface $persistence
   * @param \Curator\AppManager $app_manager
   * @param \Curator\Authorization\InstallationAgeInterface $installation_age
   * @param string $curator_filename
   */
  function __construct(SessionInterface $session, StatusService $statusService, PersistenceInterface $persistence, AppManager $app_manager, InstallationAgeInterface $installation_age, $curator_filename) {
    $this->session = $session;
    $this->status = $statusService;
    $this->persistence = $persistence;
    $this->app_manager = $app_manager;
    $this->installation_age = $installation_age;
    $this->curator_filename = $curator_filename;
  }

  public function requireAuthenticated() {
    if (! $this->isAuthenticated()) {
      throw new AccessDeniedHttpException('This request must be authenticated.');
    }
    return NULL;
  }

  public function requireAuthenticatedOrNoAuthenticationConfigured() {
    if (! $this->isAuthenticated()) {
      if ($this->isNoAuthenticationConfigured()) {
        if ($this->isNewInstallation()) {
          return NULL;
        } else {
          throw new AccessDeniedHttpException(
            "This installation of Curator was not configured with an initial username and password or other authentication mechanism in a timely fashion. 
            To protect your website, access has been locked. To fix this, delete and reinstall the Curator file(s), and then set up your login information within " . self::UNCONFIGURED_NEGLECT_HOURS . " hours.");
        }
      } else {
        throw new AccessDeniedHttpException('This request must be authenticated.');
      }
    }
    return NULL;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   * @return bool
   */
  public function isAuthenticated() {
    if (! $this->session->get('IsAuthenticated') === TRUE) {
      return FALSE;
    }
    return TRUE;
  }

  public function isNoAuthenticationConfigured() {
    if ($this->app_manager->getRunMode() === AppManager::RUNMODE_STANDALONE) {
      $this->persistence->beginReadOnly();
      $auth_mechs = $this->persistence->get('AuthenticationMechanisms', '');
      $this->persistence->end();
      return $auth_mechs === '';
    } else {
      return FALSE;
    }
  }

  public function isNewInstallation() {
    $install_time = $this->installation_age->getInstallationTime($this->curator_filename);
    if ($install_time === FALSE) {
      return FALSE;
    }

    $install_age = time() - $install_time;
    if ($install_age <= self::UNCONFIGURED_NEGLECT_HOURS * 60 * 60) {
      return TRUE;
    }
    return FALSE;
  }
}
