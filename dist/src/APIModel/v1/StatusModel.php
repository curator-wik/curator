<?php


namespace Curator\APIModel\v1;


class StatusModel {
  /**
   * @var bool $ready
   *   Simple boolean indicator of API readiness.
   */
  public $ready = FALSE;

  /**
   * @var bool $is_configured
   *   Whether this Curator installation has been configured.
   */
  public $is_configured = FALSE;

  /**
   * @var bool $needs_authentication
   *   Whether the client is recognized as authenticated.
   */
  public $is_authenticated = FALSE;

}
