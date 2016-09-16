<?php

namespace Curator\FSAccess;


interface FtpConfigurationProviderInterface
{
  /**
   * Gets the hostname of the FTP server.
   *
   * @return string
   */
  function getHostname();

  /**
   * Gets the port the FTP server accepts initial connections on.
   * NULL indicates the standard port.
   *
   * @return int|null
   */
  function getPort();

  /**
   * Gets the username for authenticated FTP connections.
   *
   * @return string|null
   *   When a non-anonymous connection is to be used, returns the username.
   *   NULL indicates an anonymous connection should be used.
   */
  function getUsername();

  /**
   * Gets the password for authenticated FTP connections.
   *
   * @return string|null
   *   When a non-anonymous connection is to be used, returns the password.
   *   NULL indicates an anonymous connection should be used.
   */
  function getPassword();
}
