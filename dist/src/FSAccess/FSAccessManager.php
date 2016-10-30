<?php


namespace Curator\FSAccess;


class FSAccessManager {

  /**
   * @var ReadAdapterInterface $readOps
   */
  protected $readOps;

  /**
   * @var WriteAdapterInterface $writeOps
   */
  protected $writeOps;

  /**
   * @var string $workingPath
   */
  protected $workingPath;

  /**
   * @var string $writeWorkingPath
   */
  protected $writeWorkingPath;

  /**
   * @var string $readSeparator
   *   The preferred directory separator character(s) for the read adapter.
   */
  protected $readSeparator;

  /**
   * @var string $writeSeparator
   *   The preferred directory separator character(s) for the write adapter.
   */
  protected $writeSeparator;

  public function __construct(ReadAdapterInterface $read_ops, WriteAdapterInterface $write_ops) {
    $this->readOps = $read_ops;
    $this->writeOps = $write_ops;

    $this->readSeparator = $this->readOps->getPathParser()->getDirectorySeparators()[0];
    $this->writeSeparator = $this->writeOps->getPathParser()->getDirectorySeparators()[0];
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
    if (! $this->readOps->getPathParser()->pathIsAbsolute($dir)) {
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

  public function setWriteWorkingPath($dir) {
    $this->writeWorkingPath = $dir;
  }

  /**
   * Probes the read and write adapters to try and identify the path
   * prefix the write adapter uses to reference the working path.
   *
   * The working path is reported by the read adapter; write adapters
   * may be chroot()ed ftp daemons etc.
   *
   * TODO: Do this processing in a batch once that API is in place.
   *
   * @return string
   *   The absolute path that, to the write adapter, is the equivalent of the
   *   working path.
   * @throws FileException
   *   If auto-detection could not identify the corresponding path on the
   *   write adapter.
   */
  public function autodetectWriteWorkingPath() {
    try {
      $working_path_items = $this->readOps->ls($this->workingPath);
      $separator = $this->readOps->getPathParser()
        ->getDirectorySeparators()[0];

      $working_path = str_replace($this->readOps->getPathParser()
        ->getDirectorySeparators(),
        $separator,
        $this->workingPath);
      $working_path_components = explode($separator, $working_path);
      $working_path_components = array_values(
        array_filter($working_path_components, function($v) {
          return $v !== '';
        })
      );

      // Is the start directory of the write adapter already the working path?
      $write_cwd = $this->writeOps->getCwd();
      if (! empty($write_cwd)) {
        $write_path = $this->autodetectPath_descend($write_cwd, $working_path_components, $working_path_items);
        if ($write_path !== NULL) {
          return $write_path;
        }
      }

      /* If that didn't find anything, also try the write adapter's root.
       * This may fail on strange write adapters that don't describe their
       * root by a single directory separator, or if we don't have permission,
       * but at that point the thrown FileException is probably just fine.
       */
      $write_separator = $this->writeOps->getPathParser()->getDirectorySeparators()[0];
      $write_path = $this->autodetectPath_descend($write_separator, $working_path_components, $working_path_items);
      if ($write_path !== NULL) {
        return $write_path;
      }
    } catch (\Exception $e) {
      throw new FileException("Failed to auto-detect path for writing.", NULL, 1, $e);
    }
    throw new FileException('Auto-detection could not locate the path for writing.', NULL, 0);
  }

  protected function autodetectPath_descend($write_starting_point, $working_path_components, $working_path_items) {
    $write_curr_dir_items = $this->writeOps->ls($write_starting_point);

    // Is the starting point already the read working path?
    if ($working_path_items === $write_curr_dir_items
      && $this->verifyWriteWorkingPathCandidate($write_starting_point)
    ) {
      return $write_starting_point;
    }

    // Okay, let's look harder.
    $working_path_starting_points = array_intersect($write_curr_dir_items, $working_path_components);
    $write_separator = $this->writeOps->getPathParser()->getDirectorySeparators()[0];

    for ($i = 0; $i < count($working_path_components); $i++) {
      $component = $working_path_components[$i];
      // Are any of the directories that make up the working path present
      // in $write_curr_dir_items? If so, explore whether they contain the
      // remainder of the tree.
      if (in_array($component, $working_path_starting_points, TRUE)) {
        $write_partial_path = $this->writeOps->simplifyPath($write_starting_point . $write_separator . $component);
        $write_curr_dir_items = $this->writeOps->ls($write_partial_path);
        $j = $i + 1;
        while ($j < count($working_path_components)) {
          if (in_array($working_path_components[$j], $write_curr_dir_items, TRUE)) {
            $write_partial_path .= $write_separator . $working_path_components[$j];
            $write_curr_dir_items = $this->writeOps->ls($write_partial_path);
            $j++;
          } else {
            $j = PHP_INT_MAX;
          }
        }

        if ($j != PHP_INT_MAX) {
          // We've fully populated the $write_partial_path. Now just see if
          // the resulting location is the same as the working path.
          //$write_curr_dir_items = $this->writeOps->ls($write_partial_path);
          if ($working_path_items === $write_curr_dir_items
            && $this->verifyWriteWorkingPathCandidate($write_partial_path)) {
            return $write_partial_path;
          }
        }
      }
    }
    return NULL;
  }

  /**
   * Tests whether a $candidate_write_path corresponds to the working path.
   *
   * The test involves a write to a file in $candidate_write_path, an attempt to
   * read it back from the working path, and a deletion of the test file.
   *
   * @param $candidate_write_path
   *   The write path that may correspond to the (read) working path.
   * @return bool
   *   TRUE if the $candidate_write_path tests out ok, FALSE otherwise.
   */
  protected function verifyWriteWorkingPathCandidate($candidate_write_path) {
    $data = time() . "\t$candidate_write_path";
    $filename = '.curator_path_verification.tmp';

    try {
      $path = $candidate_write_path
        . $this->writeOps->getPathParser()->getDirectorySeparators()[0]
        . $filename;
      $this->writeOps->filePutContents($path, $data);
    } catch (FileException $e) {
      try {
        // TODO: delete once write adapters support that operation
        // $this->writeOps->rm($path);
      } catch (FileException $e) { }
      return FALSE;
    }

    $readback_data = FALSE;
    try {
      $read_path = $this->workingPath
        . $this->readOps->getPathParser()->getDirectorySeparators()[0]
        . $filename;
      $readback_data = $this->readOps->fileGetContents($read_path);
    } catch (FileException $e) { }

    try {
      // TODO: delete once write adapters support that operation
      //$this->writeOps->rm($path);
    } catch (FileException $e) { }

    return $data === $readback_data;
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
   * @param bool $resolve_symlink
   *   If the file specified by the path is a symlink, whether to resolve it.
   *
   *   Passing FALSE is useful when you wish to operate on the symbolic link,
   *   and not the file it points to.
   *
   *   Note that this affects only the last element of $path; symlinked parent
   *   directories are always resolved.
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
  protected function normalizePath($path, $alt_wd = NULL, $resolve_symlink = TRUE) {
    $wd = $this->workingPath;
    if ($alt_wd !== NULL) {
      $wd = $alt_wd;
    }

    if ($wd === NULL) {
      throw new \LogicException('setWorkingPath() must have been called as a precondition to using normalizePath()');
    }

    $abs_path = $this->readOps->realPath($path, $wd, $resolve_symlink);

    // ensureTerminatingSeparator() to avoid similarly-named dir attacks.
    // On case-insensitive systems, this could result in false positives.
    if (strpos(
      $this->ensureTerminatingSeparator($abs_path),
      $this->ensureTerminatingSeparator($wd)
    ) !== 0) {
      throw new \InvalidArgumentException(sprintf('Path "%s" is not within working path "%s".', $path, $wd));
    }

    return $abs_path;
  }

  protected function ensureTerminatingSeparator($path) {
    $n_path = $this->readOps->getPathParser()->normalizeDirectorySeparators($path);
    foreach ($this->readOps->getPathParser()->getDirectorySeparators() as $sep) {
      if (strrpos($sep, $n_path) === strlen($n_path) - 1) {
        return $path;
      }
    }

    return $path . $this->readOps->getPathParser()->getDirectorySeparators()[0];
  }

  /**
   * Precondition: Both the working path and write working path have been set.
   *
   * @param string $normalized_path
   *   A path that has been successfully passed through
   *   FSAccessManager::normalizePath() without an $alt_wd.
   */
  protected function toWritePath($normalized_path) {
    // Path by def'n begins with $this->workingPath; make a relative path.
    $relative_path = substr($normalized_path, strlen($this->workingPath));
    if ($this->readOps->getPathParser()->pathIsAbsolute($relative_path)) {
      // TODO
    }
    $write_path = $this->writeWorkingPath
      . $this->writeOps->getPathParser()->getDirectorySeparators()[0]
      . $this->readOps->getPathParser()->translate($relative_path, $this->writeOps->getPathParser());

    return $write_path;
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
      throw new FileException(sprintf('Read via %s failed.', $filename, $this->readOps->getAdapterName()), $filename);
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
    // Normalize the path of the directory, because normalized paths must exist.
    $location = $this->readOps->simplifyPath($filename . $this->readSeparator . '..');
    $location = $this->normalizePath($location);
    $filename = $location . $this->readSeparator . basename($filename);
    $write_filename = $this->toWritePath($filename);
    // todo: Swap out absolute base of read path with that of write adapter.
    $bytes = $this->writeOps->filePutContents($write_filename, $data);
    if ($bytes != strlen($data)) {
      throw new FileException(sprintf('Write file "%s" via %s incomplete: %d of %d bytes were written.',
        $write_filename,
        $this->writeOps->getAdapterName(),
        $bytes,
        strlen($data)
      ), $filename, 1);
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
  public function mv($old_name, $new_name) {
    $old_name = $this->normalizePath($old_name, NULL, FALSE);
    $new_location = $this->readOps->simplifyPath($new_name . $this->readSeparator . '..');
    $new_location = $this->normalizePath($new_location);
    $new_name = $new_location . $this->readSeparator . $this->readOps->getPathParser()->baseName($new_name);

    $new_name = $this->readOps->getPathParser()->translate($new_name, $this->writeOps->getPathParser());
    $old_name = $this->readOps->getPathParser()->translate($old_name, $this->writeOps->getPathParser());
    $this->writeOps->rename($old_name, $new_name);
  }

  /**
   * Deletes the item at $path from the filesystem.
   *
   * This convenience method performs a read to determine the type of $path,
   * and calls unlink() or rmDir() as appropriate.
   *
   * @param string $path
   *   The path to delete.
   * @return void
   * @throws FileNotFoundException
   *   When the $path to delete does not exist.
   * @throws FileException
   *   On permission or I/O errors.
   */
  public function rm($path) {
    $path = $this->normalizePath($path, NULL, FALSE);
    if ($this->readOps->isDir($path)) {
      $this->rmDir($path);
    } else {
      $this->unlink($path);
    }
  }

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
  public function unlink($filename) {
    $filename = $this->normalizePath($filename, NULL, FALSE);
    $filename = $this->readOps->getPathParser()->translate($filename, $this->writeOps->getPathParser());
    $this->writeOps->unlink($filename);
  }

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
  public function rmDir($path) {
    $path = $this->normalizePath($path, NULL, FALSE);
    $path = $this->readOps->getPathParser()->translate($path, $this->writeOps->getPathParser());
    $this->writeOps->rmDir($path);
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
    // TODO: removed this at cabin, seems redundant? $path = $this->simplifyPath($path);
    $dirs_needed = [];
    try {
      $this->normalizePath($path);
      throw new FileExistsException($path, 0);
    } catch (FileNotFoundException $e) {
      array_unshift($dirs_needed, $this->readOps->getPathParser()->baseName($path));
      $parent = $this->readOps->simplifyPath($path . $this->readSeparator . '..');
    }

    while ($parent != '.') {
      try {
        $parent = $this->normalizePath($parent);
        break;
      } catch (FileNotFoundException $e) {
        if ($create_parents !== TRUE) {
          throw $e;
        }
        array_unshift($dirs_needed, $this->readOps->getPathParser()->baseName($parent));
        $parent = $this->readOps->simplifyPath($parent . $this->readSeparator . '..');
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
    $existing_dirs = $this->readOps->getPathParser()->translate($parent, $this->writeOps->getPathParser());

    foreach ($dirs_needed as $new_dir) {
      $existing_dirs .= $this->writeSeparator . $new_dir;
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
    try {
      $filename = $this->normalizePath($filename);
    } catch (FileNotFoundException $e) {
      return FALSE;
    }
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
    try {
      $path = $this->normalizePath($path);
    } catch (FileNotFoundException $e) {
      return FALSE;
    }
    return $this->readOps->isDir($path);
  }

}
