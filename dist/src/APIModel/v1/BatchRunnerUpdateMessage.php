<?php


namespace Curator\APIModel\v1;

/**
 * Class BatchRunnerUpdateModel
 *   Models information about the progress and current activity of a batch
 *   Task Runner while the run is in progress.
 */
class BatchRunnerUpdateMessage extends BatchRunnerMessage {

  public function __construct() {
    $this->type = BatchRunnerMessage::TYPE_UPDATE;
    $this->ok = TRUE; // Default to something truthy.
  }

  /**
   * @var int $num_completed
   *   Number of Runnables completed during this Incarnation of this Runner.
   */
  public $n;

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
