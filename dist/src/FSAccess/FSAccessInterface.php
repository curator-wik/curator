<?php


namespace Curator\FSAccess;

/**
 * Interface FSAccessInterface
 *   Abstraction of basic filesystem operations.
 *
 * @package Curator\FSAccess
 */
interface FSAccessInterface {

  /**
   * Sets the path where filesystem accesses with a relative path will be
   * performed relative to.
   *
   * @param string $dir
   *   The full path where subsequent filesystem accesses will occur.
   *
   *   This should be set per the mounted (possibly read-only) filesystem, not
   *   the path used for write operations, which may be appear to us to be
   *   chroot'ed.
   *
   * @return void
   *
   * @throws \InvalidArgumentException
   *   When $dir is not a directory readable to the process.
   */
  function setWorkingPath($dir);

  /**
   * Reads an entire file at $filename into a string.
   *
   * If need arises to set offset/max length, it should probably be in an
   * extension interface.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path.
   *
   * @return string
   * @throws FileNotFoundException
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  function fileGetContents($filename);

  /**
   * Writes an entire string to $filename, overwriting it if it already exists.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path.
   * @param string $data
   *   The string that should be made the file's contents.
   * @return int
   *   The number of bytes that were written to the file.
   * @throws FileNotFoundException
   *   If a directory in the path to $filename is not found.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  function filePutContents($filename, $data);

  /**
   * Moves existing file with $old_name to $new_name, overwriting if necessary.
   *
   * @param string $old_name
   *   Absolute path, or relative path under the working path.
   * @param string $new_name
   *   Absolute path, or relative path under the working path.
   * @return void
   * @throws FileNotFoundException
   *   When the $old_name does not exist, or a directory in $new_name is not
   *   found.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $old_name or $new_name is outside the working path.
   */
  function mv($old_name, $new_name);

  /**
   * Makes new directories.
   *
   * @param string $path
   *   Absolute path, or relative path under the working path.
   * @param bool $create_parents
   *   Make parent directories as needed.
   * @return void
   * @throws FileNotFoundException
   *   When a non-leaf directory of $path is not found and $create_parents is
   *   false.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  function mkdir($path, $create_parents = FALSE);

}
