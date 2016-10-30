<?php

namespace Curator\Tests\Integration\FSAccess;

use Curator\FSAccess\FtpConfigurationProviderInterface;

// Later on I'll do one of these for Curator UI and one for cloud infrastructure
// pushes. Each will use Persistence for everything but password. For password,
// maybe have the client (UI or cloud) send an encrypted form of it in a header
// on each request that involves a write?

class TestFtpConfigurationProvider implements FtpConfigurationProviderInterface
{
  public $username;

  public function __construct($username = NULL) {
    if (empty($username)) {
      $username = 'ftptest';
    }
    $this->username = $username;
  }

  public function getHostname() {
    return 'localhost';
  }

  public function getUsername() {
    return $this->username;
  }

  public function getPassword() {
    return 'Asdf1234';
  }

  public function getPort() {
    return NULL;
  }
}
