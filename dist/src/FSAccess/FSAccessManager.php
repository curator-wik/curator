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
    $orig_wd = $this->writeOps->getcwd();

    if (! $this->chdir($wd)) {
      throw new FileException(sprintf('Unable to make %s the working directory.', $wd));
    }

    if (! @$this->readOps->pathExists($path)) {
      if ($orig_wd) {
        $this->chdir($orig_wd);
      }
      throw new FileNotFoundException($path);
    }
    $abs_path = $this->readOps->realPath($path);
    if ($orig_wd) {
      $this->chdir($orig_wd);
    }

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
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  public function fileGetContents($filename) {
    $filename = $this->normalizePath($filename);
    if ($data = $this->readOps->fileGetContents($filename) === FALSE) {
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
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  function mkdir($path, $create_parents = FALSE) {
    $location = $this->simplifyPath($path);
    // Catch relative paths that ascend above the working path right now.
    if (strncmp($location, './../', 5) === 0) {
      throw new \InvalidArgumentException(sprintf('Will not create directory "%s" outside the working path.', $path));
    }

    if ($create_parents) {
      $existing_path = NULL;
      $new_depth = 1;
      do {
        $location = dirname($location);
        if ($location != '.') {
          $new_depth++;
        }
        try {
          $existing_path = $this->normalizePath($location);
        } catch (FileNotFoundException $e) {
          // Let the loop try one more level up.
        }
      } while($existing_path === NULL && $location != '/' && $location != '.');

      if ($existing_path === NULL) {
        throw new FileException(sprintf('Unable to find location in existing directory tree to attach new directories in path: %s', $path));
      }

      // Locate the portion of $path that needs to be created.

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

  /**
   * Changes the current directory for both read and write operations.
   *
   * Consumers of the public API should use setWorkingPath().
   *
   * @param string $directory
   *   The directory to change to.
   * @return bool
   *   TRUE on success or FALSE on failure.
   */
  protected function chdir($directory) {
    $write_wd = $this->writeOps->getcwd();
    if ($this->writeOps->chdir($directory)) {
      if ($this->readOps->chdir($directory)) {
        return TRUE;
      } else if ($write_wd) {
        $this->writeOps->chdir($write_wd);
      }
    }

    return FALSE;
  }
}
