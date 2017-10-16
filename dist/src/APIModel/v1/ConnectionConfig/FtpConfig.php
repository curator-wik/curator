<?php


namespace Curator\APIModel\v1\ConnectionConfig;

/**
 * Class FTPConfig
 *
 * @SWG\Definition()
 */
class FtpConfig {
  /**
   * The FTP username.
   * @var string $username
   * @SWG\Property()
   */
  public $username;

  /**
   * The FTP password.
   * @var string $password
   * @SWG\Property()
   */
  public $password;

  /**
   * The FTP server hostname.
   *
   * @var string $host
   */
  public $host;

  /**
   * The FTP server's TCP port.
   *
   * @var int $port
   */
  public $port;
}
