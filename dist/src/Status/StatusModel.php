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
   * @var string|null $adjoining_app_targeter
   *   DI container service name of a built-in application targeter.
   *   If NULL, the IntegrationConfig can still provide an AppTargeterInterface
   *   implementation.
   */
  public $adjoining_app_targeter = NULL;

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

  /**
   * @var string $site_root
   *   The mounted, possibly read-only path to the root of the adjoining application.
   */
  public $site_root;

  /**
   * @var string $rollback_capture_path
   *   A location under the site_root where backup copies of modified files are stored.
   */
  public $rollback_capture_path;

  /**
   * @var string $timezone
   *   The user's preferred timezone
   */
  public $timezone;

  /**
   * @var string $write_working_path
   *   The path corresponding to the site root for write operations.
   */
  public $write_working_path;

}
