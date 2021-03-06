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
   * Sets the path where filesystem reads with a relative path will be
   * performed relative to.
   *
   * @param string $dir
   *   The full path where subsequent filesystem reads will occur.
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
   * Sets the path where writes with a relative path will be performed relative
   * to.
   * This is potentially as distinct from the working path set by
   * setWorkingPath(), in case for example of chroot()ed FTP.
   *
   * @see FSAccessManager::autodetectWriteWorkingPath()
   *
   * @param string $dir
   *   The full path where subsequent filesystem writes will occur.
   * @return void
   */
  function setWriteWorkingPath($dir);

  /**
   * Indicates whether setWorkingPath() has been successfully called.
   *
   * @return bool
   */
  function isWorkingPathSet();

  /**
   * Indicates whether setWriteWorkingPath() has been successfully called.
   *
   * @return bool
   */
  function isWriteWorkingPathSet();

  /**
   * Provides a path terminated with a read adapter's directory separator.
   *
   * @param string $path
   *   The path you wish to have terminated with a directory separator.
   * @return string
   *   The path you provided, with a directory separator appended if one was
   *   not present.
   */
  function ensureTerminatingSeparator($path);

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
   * Moves existing file with $old_name to $new_name.
   *
   * If $new_name exists and is not a directory, it will be overwritten.
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
   *   Resulting from permission or I/O errors, including when $new_name is a
   *   directory.
   * @throws \InvalidArgumentException
   *   If $old_name or $new_name is outside the working path.
   */
  function mv($old_name, $new_name);

  /**
   * Deletes the item at $path from the filesystem.
   *
   * This convenience method performs a read to determine the type of $path,
   * and calls unlink() or rmDir() as appropriate.
   *
   * @param string $path
   *   The path to delete.
   * @param bool $recursive
   *   Whether to delete any children of the path as well.
   *
   *   If FALSE and the path has children, an exception will be thrown instead.
   *
   *   This should only be applied to directories containing a reasonable number of
   *   descendants. Directories that might be very large should be removed via a batch
   *   task.
   * @return void
   * @throws FileNotFoundException
   *   When the $path to delete does not exist.
   * @throws FileException
   *   On permission or I/O errors.
   */
  function rm($path, $recursive = FALSE);

  /**
   * Unlinks (deletes) a non-directory filesystem object.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path of file to unlink.
   * @return void
   * @throws FileNotFoundException
   *   When the file to delete does not exist.
   * @throws FileException
   *   On permission or I/O errors.
   */
  public function unlink($filename);

  /**
   * Deletes an empty directory from the filesystem.
   *
   * @param string $path
   *   Absolute path, or relative path under the working path of dir to delete.
   * @return void
   * @throws FileNotFoundException
   *   When the directory to delete does not exist.
   * @throws FileException
   *   On permission or I/O errors.
   */
  public function rmDir($path);

  /**
   * Makes new directories.
   *
   * @param string $path
   *   Absolute path, or relative path under the working path.
   * @param bool $create_parents
   *   Make parent directories as needed.
   * @param bool $no_op
   *   Determine which directories need to be created, but do not create them.
   * @return void
   * @throws FileNotFoundException
   *   When a non-leaf directory of $path is not found and $create_parents is
   *   false.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  function mkdir($path, $create_parents = FALSE, $no_op = FALSE);

  /**
   * Determines whether a path exists and is a regular file.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path.
   * @param bool $resolve_symlinks
   *   Whether to follow symlinks and report on their targets.
   * @return bool
   *   TRUE if the file exists, FALSE otherwise.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public function isFile($filename, $resolve_symlinks = TRUE);

  /**
   * Determines whether a path exists and is a directory.
   *
   * @param string $path
   *   Absolute path, or relative path under the working path.
   * @param bool $resolve_symlinks
   *   Whether to follow symlinks and report on their targets.
   * @return bool
   *   TRUE if the directory exists, FALSE otherwise.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public function isDir($path, $resolve_symlinks = TRUE);

  /**
   * Lists the contents of a directory.
   *
   * @param $path
   *   Absolute path, or relative path under the working path.
   * @return string[]
   *   An array containing the file and directory names found within the path.
   *   '.' and '..' are not included.
   * @throws FileException
   *   If a directory listing cannot be obtained from the given path.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public function ls($path);

}
