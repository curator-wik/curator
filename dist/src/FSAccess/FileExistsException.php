<?php


namespace Curator\FSAccess;


/**
 * Thrown when a file already exists.
 */
class FileExistsException extends FileException
{
  /**
   * Constructor.
   *
   * @param string $path
   *   The path to the file that already exists.
   * @param int $code
   *   @inheritdoc
   */
  public function __construct($path, $code = 0)
  {
    parent::__construct(sprintf('The file "%s" already exists', $path), $path, $code);
  }
}
