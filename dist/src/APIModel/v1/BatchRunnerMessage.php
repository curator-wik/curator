<?php


namespace Curator\APIModel\v1;


class BatchRunnerMessage {
  const TYPE_CONTROL = 0;
  const TYPE_UPDATE = 1;
  const TYPE_RESPONSE = 2;

  /**
   * @var int $type
   *   One of the TYPE_ constants.
   */
  public $type;

  public function toJson() {
    return json_encode($this);
  }
}
