<?php


namespace Curator\APIModel\v1;


class BatchRunnerControlMessage extends BatchRunnerMessage {
  public function __construct($runner_id, $incomplete_runner_ids) {
    $this->type = BatchRunnerMessage::TYPE_CONTROL;

    $this->runner_id = $runner_id;
    $this->incomplete_runner_ids = $incomplete_runner_ids;
  }

  public $runner_id;

  /**
   * @var int[]
   *   Runner ids the client should issue additional requests for.
   */
  public $incomplete_runner_ids;
}
