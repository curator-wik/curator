<?php


namespace Curator\APIModel\v1;

/**
 * Class BatchRunnerRawProgressMessage
 *
 * An alternate type of update message that provides a precomputed percent
 * complete rather than the number of completed runnables.
 */
class BatchRunnerRawProgressMessage extends BatchRunnerMessage {
  public function __construct() {
    $this->type = BatchRunnerMessage::TYPE_UPDATE;
    $this->ok = TRUE; // Default to something truthy.
  }

  /**
   * @var float $pct
   *   The total percent complete for this Task that should be directly reported
   *   in the UI.
   */
  public $pct;

  /**
   * @var string[] $chatter
   *   Informational messages the Runner may have for user enrichment.
   *   These are not guaranteed to be displayed, or may be displayed for a
   *   short time, so they should not be important.
   */
  public $chatter;

  /**
   * @var bool $ok
   *   Boolean indicating whether there has been an error.
   */
  public $ok;

}