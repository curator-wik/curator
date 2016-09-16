<?php

namespace Curator\Persistence;

/**
 * Class InvalidPersistedValueException
 *   Indicates a value retrieved from the persistence was found to be invalid.
 */
class InvalidPersistedValueException extends \Exception {
  /**
   * @var string $key
   *   The key corresponding to the invalid value.
   */
  protected $key;

  public function __construct($message, $key, $code = 0, \Exception $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->key = $key;
  }

  public function getKey() {
    return $this->key;
  }
}
