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

  public function realPath($path, $relative_to = NULL) {
    if (! $this->getPathParser()->pathIsAbsolute($path)) {
      $separator = $this->getPathParser()->getDirectorySeparators();
      $separator = reset($separator);
      $path = $relative_to
          . $separator
          . $path;
    }

    if ($this->getStreamContext()->getScheme() === 'file://') {
      // We can resolve symlinks and such if using the filesystem.
      return realpath($path);
    } else {
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
