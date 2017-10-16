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
   *
   * @var string
   * @SWG\Property()
   */
  public $username;

  /**
   * The FTP password.
   *
   * @var string
   * @SWG\Property()
   */
  public $password;

  /**
   * The FTP server hostname.
   *
   * @var string
   * @SWG\Property()
   */
  public $host;

  /**
   * The FTP server's TCP port.
   *
   * @var int
   * @SWG\Property()
   */
  public $port;
}
