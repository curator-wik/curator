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

  public function __construct(\Curator\Status\StatusModel $copy_from = NULL) {
    if ($copy_from !== NULL) {
      $this->ready = $copy_from->ready;
      $this->is_configured = $copy_from->is_configured;
      $this->is_authenticated = $copy_from->is_authenticated;
    }
  }
}
