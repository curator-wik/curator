<?php


namespace Curator\FSAccess;


use Curator\Persistence\PersistenceInterface;

class DefaultFtpConfigurationProvider implements FtpConfigurationProviderInterface {
  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;
  protected $settings_cache;

  public function __construct(PersistenceInterface $persistence) {
    $this->persistence = $persistence;
    $this->settings_cache = NULL;
  }

  protected function lazyLoad() {
    if ($this->settings_cache === NULL) {
      $this->persistence->beginReadOnly();
      foreach(['ftp.port', 'ftp.username', 'ftp.hostname'] as $key) {
        $this->settings_cache[$key] = $this->persistence->get($key);
      }
      $this->persistence->end();
    }
  }

  public function getPort() {
    $this->lazyLoad();
    return $this->settings_cache['ftp.port'];
  }

  public function getHostname() {
    $this->lazyLoad();
    return $this->settings_cache['ftp.hostname'];
  }

  public function getUsername() {
    $this->lazyLoad();
    return $this->settings_cache['ftp.username'];
  }

  public function getPassword() {
    return $_SESSION['ftp.password'];
  }
}
