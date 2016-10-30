<?php


namespace Curator\FSAccess;


/**
 * Thrown when an error occurred in the component File.
 */
class FileException extends \RuntimeException
{
  /**
   * @var string $path
   *   The affected path.
   */
  protected $path;

  public function __construct($message, $path = NULL, $code = 0, \Exception $previous = NULL) {
    if (is_string($path)) {
      $this->path = $path;
      $message = "Path \"$path\": " . $message;
    }

    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the path affected by the failure, if available.
   *
   * @return string|null
   *   The path affected by the failure, or NULL in case it is unknown.
   */
  public function getPath() {
    return $this->path;
  }
}
