<?php


namespace Curator\FSAccess;

/**
 * Interface FSReadInterface
 *   The operations which readable filesystem adapters must support.
 */
interface ReadAdapterInterface {
  /**
   * Returns a well-known name for the underlying file access method.
   *
   * E.g. "ftp" or "mounted filesystem". Useful in user error messages.
   *
   * @return string
   */
  function getAdapterName();

  /**
   * Returns an absolute pathname, canonicalized if the adapter supports it.
   *
   * Relative paths are made absolute relative to the $relative_to path. It is
   * a logic error to invoke this method with a relative $path and an empty
   * $relative_to.
   *
   * The resulting path will always resolve references to '/./' and '/../', and
   * will exclude extraneous directory separator characters. When the filesystem
   * is being accessed through a facility that can detect and resolve symlinks,
   * the result will also be canonicalized: that is to say, the path returned
   * will have all symlinks expanded.
   *
   * @param string $path
   *   The path to make absolute.
   * @param string $relative_to
   *   When $path is relative, the path to resolve it relative to.
   * @return string
   *   The absolute pathname.
   * @throws FileNotFoundException
   *   If the path does not exist.
   * @throws FileException
   *   On permission or I/O errors.
   */
  function realPath($path, $relative_to = NULL);

  /**
   * Determines whether anything exists at $path.
   *
   * @param string $path
   *   Absolute path to test.
   * @return bool
   * @throws FileException
   */
  function pathExists($path);

  /**
   * Determines whether a directory exists at $path.
   *
   * @param string $path
   *   Absolute path to test.
   * @return bool
   * @throws FileException
   */
  function isDir($path);

  /**
   * Determines whether a regular file exists at $path.
   *
   * @param string $path
   *   Absolute path to test.
   * @return bool
   * @throws FileException
   */
  function isFile($path);

  /**
   * Reads an entire file at $filename into a string.
   *
   * If need arises to set offset/max length, it should probably be in an
   * extension interface.
   *
   * @param string $filename
   *   Absolute path of file to get.
   *
   * @return string
   * @throws FileNotFoundException
   * @throws FileException
   *   Resulting from permission or I/O errors.
   */
  function fileGetContents($filename);

  /**
   * Gets a Path Parser for the path strings this adapter uses.
   *
   * @return PathParser\PathParserInterface
   */
  function getPathParser();

  /**
   * Gets an array of item names in the specified $directory.
   *
   * The FSAccessManager uses this during auto-detection of the write
   * adapter path corresponding to the read adapter's working path.
   *
   * @param string $directory
   *   The directory to list.
   *   Unlike WriteAdapterInterface::ls(), relative paths are not allowed with
   *   this method.
   * @return string[]
   *   Names found in the directory, in alphabetical order.
   *   The '.' and '..' special entries are excluded.
   * @throws FileException
   *   Resulting from permission, I/O, and missing path errors.
   */
  function ls($directory);

  /**
   * Removes extraneous elements from a path by path parsing only.
   *
   * For example, paths that include consecutive directory separators or
   * ascend towards the root with "../../" are rewritten. For filesystem-aware
   * path simplification, e.g. resolution of symlinks, use realPath().
   *
   * @param string $path
   *   The path to simplify.
   * @return string
   *   The simplified path.
   */
  function simplifyPath($path);
}
