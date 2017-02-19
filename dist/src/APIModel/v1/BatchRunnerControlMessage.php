<?php


namespace Curator\APIModel\v1;


class BatchRunnerControlMessage extends BatchRunnerMessage {
  public function __construct($runner_id, $again) {
    $this->type = BatchRunnerMessage::TYPE_CONTROL;

    $this->runner_id = $runner_id;
    $this->again = $again;
  }

  public $runner_id;

  /**
   * @var bool
   *   Whether the client should issue another request with this runner id
   */
  public $again;
}
