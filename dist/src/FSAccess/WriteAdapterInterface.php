<?php


namespace Curator\FSAccess;

/**
 * Interface FSWriteInterface
 *   The operations which writable filesystem adapters must support.
 */
interface WriteAdapterInterface {
  /**
   * Returns a well-known name for the underlying file access method.
   *
   * E.g. "ftp" or "mounted filesystem". Useful in user error messages.
   *
   * @return string
   */
  function getAdapterName();

  /**
   * Gets an array of item names in the specified $directory.
   *
   * The FSAccessManager uses this during auto-detection of the write
   * adapter path corresponding to the read adapter's working path.
   *
   * @param string $directory
   *   The directory to list.
   *   Relative paths including '.' are accepted here; they are
   *   relative to whatever the backing system's working directory is.
   * @return string[]
   *   Names found in the directory, in alphabetical order.
   *   The '.' and '..' special entries are excluded.
   */
  function ls($directory);

  /**
   * Writes an entire string to $filename, overwriting it if it already exists.
   *
   * @param string $filename
   *   Absolute path of file to write to.
   * @param string $data
   *   The string that should be made the file's contents.
   * @param bool $lock_if_able
   *   If supported, take out an advisory lock on the file before modifying it.
   * @return int
   *   The number of bytes that were written to the file.
   * @throws FileNotFoundException
   *   If a directory in the path to $filename is not found.
   *
   *   The FTP write adapter cannot detect this is the failure mode,
   *   and will throw FileException instead.
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

  /**
   * Gets a Path Parser for the path strings this adapter uses.
   *
   * @return PathParser\PathParserInterface
   */
  function getPathParser();

  /**
   * Reports the working directory of the system underlying the write adapter.
   *
   * This is primarily inspired by FTP write adapters - often FTP servers will
   * put you in a directory at or close to the path we want to be writing to
   * at login. This makes it possible for write path auto-detection to use the
   * path the FTP server selected for us as a place to start searching from.
   *
   * @return string
   *   The working directory as reported by the system underlying the write
   *   adapter.
   *
   *   If the system underlying the write adapter does not support retrieval
   *   of a working directory, it is permissible to return the empty string,
   *   although this will reduce the likelihood of the write path auto-detector
   *   succeeding.
   */
  function getCwd();

  /**
   * Removes extraneous elements from a path by path parsing only.
   *
   * For example, paths that include consecutive directory separators or
   * ascend towards the root with "../../" are rewritten.
   *
   * @param string $path
   *   The path to simplify.
   * @return string
   *   The simplified path.
   */
  function simplifyPath($path);
}
