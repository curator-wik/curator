<?php

namespace Curator\FSAccess;

// Later on I'll do one of these for Curator UI and one for cloud infrastructure
// pushes. Each will use Persistence for everything but password. For password,
// maybe have the client (UI or cloud) send an encrypted form of it in a header
// on each request that involves a write?

class TempFtpConfigurationProvider implements FtpConfigurationProviderInterface
{
  public function getHostname() {
    return 'localhost';
  }

  public function getUsername() {
    return 'ftptest';
  }

  public function getPassword() {
    return 'Asdf1234';
  }

  public function getPort() {
    return NULL;
  }
}
