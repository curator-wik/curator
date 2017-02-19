<?php


namespace Curator\Status;


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

  /**
   * @var bool $alarm_signal_works
   *   Whether pcntl_alarm and related functions are available & operational.
   */
  public $alarm_signal_works = FALSE;

  /**
   * @var bool $flush_works
   *   Whether the built-in flush() function is operational in this environment.
   */
  public $flush_works = FALSE;

}
