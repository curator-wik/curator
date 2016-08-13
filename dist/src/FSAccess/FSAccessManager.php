<?php


namespace Curator\FSAccess;


class FSAccessManager {
  use PathSimplificationTrait;

  /**
   * @var ReadAdapterInterface $readOps
   */
  protected $readOps;

  protected $writeOps;

  protected $workingPath;

  public function __construct(ReadAdapterInterface $read_ops, WriteAdapterInterface $write_ops) {
    $this->readOps = $read_ops;
    $this->writeOps = $write_ops;
  }

  /**
   * @return \Curator\FSAccess\PathParser\PathParserInterface
   */
  protected function getPathParser() {
    return $this->readOps;
  }

  /**
   * Sets the path where filesystem accesses with a relative path will be
   * performed relative to.
   *
   * @param string $dir
   *   The absolute path where subsequent filesystem accesses will occur.
   *
   *   This should be set per the mounted (possibly read-only) filesystem, not
   *   the path used for write operations, which may be appear to us to be
   *   chroot'ed.
   *
   * @return void
   *
   * @throws FileException
   *   When $dir is not accessible to the process.
   * @throws \InvalidArgumentException
   *   When $dir is not an absolute path to a directory.
   */
  public function setWorkingPath($dir) {
    if (empty($dir) || ! is_string($dir)) {
      throw new \InvalidArgumentException('$dir must be a nonempty string.');
    }
    if (! $this->readOps->pathIsAbsolute($dir)) {
      throw new \InvalidArgumentException('$dir must be an absolute path.');
    }
    if (! $this->readOps->isDir($dir)) {
      throw new \InvalidArgumentException("Not a directory: $dir");
    }

    $real_dir = $this->readOps->realPath($dir);
    // Can we do path normalization based on this path?
    $this->normalizePath('.', $real_dir);

    // Fantastic! Make this the working path.
    $this->workingPath = $real_dir;
  }

  /**
   * Transforms $path to an absolute path with symlinks etc. removed.
   *
   * Relative paths are transformed based on the working path.
   *
   * @param string $path
   *   The path to normalize.
   * @param string $alt_wd
   *   An optional alternate path to resolve relative paths from, instead of
   *   the working path.
   * @return string
   *   The normalized path.
   * @throws FileNotFoundException
   *   When the $path does not exist.
   * @throws FileException
   *   When the FSReadInterface service cannot make the working path the
   *   current directory.
   * @throws \InvalidArgumentException
   *   If the $path resolves to a location above the working path.
   * @throws \LogicException
   *   If this method is invoked before setWorkingPath() has been called and no
   *   $alt_wd was supplied.
   */
  protected function normalizePath($path, $alt_wd = NULL) {
    $wd = $this->workingPath;
    if ($alt_wd !== NULL) {
      $wd = $alt_wd;
    }

    if ($wd === NULL) {
      throw new \LogicException('setWorkingPath() must have been called as a precondition to using normalizePath()');
    }

    $abs_path = $this->readOps->realPath($path, $wd);

    // On case-insensitive systems, this could result in false positives.
    if (strpos($abs_path, $wd) !== 0) {
      throw new \InvalidArgumentException(sprintf('Path "%s" is not within working path "%s".', $path, $wd));
    }
    return $abs_path;
  }

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
   * @throws FileExistsException
   *   When the specified path exists but is a directory or other object of a
   *   non-readable type.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  public function fileGetContents($filename) {
    $filename = $this->normalizePath($filename);
    if (($data = $this->readOps->fileGetContents($filename)) === FALSE) {
      throw new FileException(sprintf('Read file "%s" via %s failed.', $filename, $this->readOps->getAdapterName()));
    }
    return $data;
  }

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
  public function filePutContents($filename, $data) {
    $location = $this->normalizePath(dirname($filename));
    $filename = $location . DIRECTORY_SEPARATOR . basename($filename);
    $bytes = $this->writeOps->filePutContents($filename, $data);
    if ($bytes != strlen($data)) {
      throw new FileException(sprintf('Write file "%s" via %s incomplete: %d of %d bytes were written.',
        $filename,
        $this->writeOps->getAdapterName(),
        $bytes,
        strlen($data)
      ), 1);
    }
    return $bytes;
  }

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
  function mv($old_name, $new_name) {
    $old_name = $this->normalizePath($old_name);
    $new_location = $this->normalizePath(dirname($new_name));
    $new_name = $new_location . DIRECTORY_SEPARATOR . basename($new_name);
    $this->writeOps->rename($old_name, $new_name);
  }

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
   * @throws FileExistsException
   *   When a filesystem object already exists at the $path (code 0), or a
   *   non-directory exists at a location along the $path (code 1).
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  function mkdir($path, $create_parents = FALSE) {
    /*
     * Creating directories is harder than it looks. This is because we need to
     * support creating parent directories as needed, but the
     * WriteAdapterInterface only supports creating one directory at a time,
     * and we need to ensure we only allow directories to be created within the
     * working path, but the ReadAdapterInterface's realPath() and by extension
     * $this->normalizePath() only work on paths that already exist.
     */
    $path = $this->simplifyPath($path);
    $dirs_needed = [];
    try {
      $this->normalizePath($path);
      throw new FileExistsException($path, 0);
    } catch (FileNotFoundException $e) {
      array_unshift($dirs_needed, basename($path));
      $parent = dirname($path);
    }

    while ($parent != '.') {
      try {
        $parent = $this->normalizePath($parent);
        break;
      } catch (FileNotFoundException $e) {
        if ($create_parents !== TRUE) {
          throw $e;
        }
        array_unshift($dirs_needed, basename($parent));
        $parent = dirname($parent);
      }
    }
    if ($parent == '.') {
      // Then the last $parent checked by normalizePath() was in the working
      // path. Either $path is absolute and the working path is /, or $path is
      // relative and relative to the working path by definition.
      $parent = $this->workingPath;
    }
    // If we get this far, $parent contains a normalized absolute path to the
    // deepest directory of $path that already exists in the filesystem and is
    // within the working path.
    $existing_dirs = $parent;

    foreach ($dirs_needed as $new_dir) {
      $existing_dirs .= '/' . $new_dir;
      $this->writeOps->mkdir($existing_dirs);
    }
  }

  /**
   * Determines whether a path exists and is a regular file.
   *
   * @param string $filename
   *   Absolute path, or relative path under the working path.
   * @return bool
   *   TRUE if the file exists, FALSE otherwise.
   *
   *   Symbolic links are treated as existing when they point to valid regular
   *   files.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public function isFile($filename) {
    $filename = $this->normalizePath($filename);
    return $this->readOps->isFile($filename);
  }

  /**
   * Determines whether a path exists and is a directory.
   *
   * @param string $path
   *   Absolute path, or relative path under the working path.
   * @return bool
   *   TRUE if the directory exists, FALSE otherwise.
   *
   *   Symbolic links are treated as existing when they point to valid
   *   directories.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public function isDir($path) {
    $path = $this->normalizePath($path);
    return $this->readOps->isDir($path);
  }

}
