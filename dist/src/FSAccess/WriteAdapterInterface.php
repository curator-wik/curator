<?php


namespace Curator\FSAccess;

/**
 * Interface FSWriteInterface
 *   The operations which writable filesystem adapters must support.
 */
interface WriteAdapterInterface extends PathParser\PathParserInterface {
  /**
   * Returns a well-known name for the underlying file access method.
   *
   * E.g. "ftp" or "mounted filesystem". Useful in user error messages.
   *
   * @return string
   */
  function getAdapterName();

  /**
   * Writes an entire string to $filename, overwriting it if it already exists.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path.
   * @param string $data
   *   The string that should be made the file's contents.
   * @param bool $lock_if_able
   *   If supported, take out an advisory lock on the file before modifying it.
   * @return int
   *   The number of bytes that were written to the file.
   * @throws FileNotFoundException
   *   If a directory in the path to $filename is not found.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   */
  function filePutContents($filename, $data, $lock_if_able = TRUE);

  /**
   * Makes a directory.
   *
   * @param $path
   *   Absolute path under the working path.
   *
   *   All components of the path except for the last must be existing
   *   directories.
   * @return void
   * @throws FileNotFoundException
   *   When the directory to create the new directory in does not already exist.
   * @throws FileExistsException
   *   When a filesystem object already exists at the $path (code 0), or the
   *   parent of the new directory is not a directory (code 1).
   * @throws FileException
   *   On permission or I/O errors.
   */
  function mkdir($path);
}
