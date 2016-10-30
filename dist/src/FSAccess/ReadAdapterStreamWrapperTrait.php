<?php

namespace Curator\FSAccess;

/**
 * Trait ReadAdapterStreamWraperTrait
 *   Implements the ReadAdapterInterface using PHP stream wrappers and a context provided by the using class.
 *
 *   As a trait, allows single classes to optionally implement both read and write adapters via a single stream context.
 */
trait ReadAdapterStreamWrapperTrait
{
  use PathSimplificationTrait;

  /**
   * @return StreamContextWrapper
   */
  public abstract function getStreamContext();

  /**
   * Helper method that attempts to determine a specific reason a path is
   * invalid and throw the most appropriate exception.
   *
   * PHP stream wrappers give you enough detail to reliably know if an
   * operation worked or not for any underlying stream type, but they aren't as
   * good at consistently providing information about the problem when there is
   * one.
   *
   * This method gives stream wrapper based read/write adapters a chance to dig
   * deeper after an operation has failed.
   *
   * @param string $path
   *   The path that an operation failed on.
   * @param string $read_write
   *   'r' if the operation was a read, 'w' if a write.
   * @param string $operation_description
   *   A string describing the operation that failed.
   * @param ErrorException $error_exception
   *   Optional ErrorException from the failed operation.
   *
   * @throws FileException
   * @throws FileNotFoundException
   */
  protected abstract function failPath($path, $read_write, $operation_description, \ErrorException $error_exception = NULL);

  /**
   * Performs path transformations necessary for the stream wrapper to
   * function.
   *
   * For example, the FTP stream wrapper requires the username and password
   * to be included in the paths as part of the full ftp:// URI.
   *
   * The base implementation provided by ReadAdapterStreamWrapperTrait
   * does nothing.
   *
   * @param $path
   *   An absolute path to alter.
   * @return string
   *   The altered path.
   */
  protected abstract function alterPathForStreamWrapper($path);

  /**
   * @see ReadAdapterInterface::realPath().
   *
   * @param string $path
   * @param string|NULL $relative_to
   * @param bool $resolve_symlink
   * @return string
   */
  public function realPath($path, $relative_to = NULL, $resolve_symlink = TRUE) {
    if (! $this->getPathParser()->pathIsAbsolute($path)) {
      $separator = $this->getPathParser()->getDirectorySeparators();
      $separator = reset($separator);
      $path = $relative_to
          . $separator
          . $path;
    }

    if ($this->getStreamContext()->getScheme() === 'file://') {
      // We can resolve symlinks and such if using the filesystem.
      if ($resolve_symlink) {
        $true_real_path = realpath($path);
      } else {
        $parent = $this->simplifyPath($path . $this->getPathParser()->getDirectorySeparators()[0] . '..');
        $true_real_path = realpath($parent);
        if ($true_real_path !== FALSE) {
          $true_real_path .= $this->getPathParser()->getDirectorySeparators()[0]
            . $this->getPathParser()->baseName($path);

          try {
            $exists = lstat($true_real_path);
            if (! $exists) {
              $true_real_path = FALSE;
            }
          } catch (\ErrorException $e) {
            $this->failPath($true_real_path, 'r', 'Unable to access the path. This may occur if directories in the path are missing, or due to permission or I/O errors.', $e);
          }
        }
      }
      if ($true_real_path !== FALSE) {
        return $true_real_path;
      } else {
        $this->failPath($path, 'r', 'Unable to access the path. This may occur if directories in the path are missing, or due to permission or I/O errors.');
      }
    } else  {
      // Best we can do is to clean up redundant things in the path.
      return $this->simplifyPath($path);
    }
  }

  // TODO: Make these throw FileException on badness
  public function pathExists($path) {
    return file_exists($this->alterPathForStreamWrapper($path));
  }

  public function isDir($path) {
    return is_dir($this->alterPathForStreamWrapper($path));
  }

  public function isFile($path) {
    return is_file($this->alterPathForStreamWrapper($path));
  }

  public function fileGetContents($filename) {
    return file_get_contents(
        $this->alterPathForStreamWrapper($filename),
        null,
        $this->getStreamContext()->getContext()
    );
  }

}
