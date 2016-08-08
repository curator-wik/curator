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
   * @param string $path The path to the file that already exists.
   */
  public function __construct($path)
  {
    parent::__construct(sprintf('The file "%s" already exists', $path));
  }
}
