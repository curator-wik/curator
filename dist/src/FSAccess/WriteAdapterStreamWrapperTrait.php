<?php

namespace Curator\FSAccess;

use Curator\Util\ErrorHandling;

trait WriteAdapterStreamWrapperTrait
{
  /**
   * @see ReadAdapterStreamWrapperTrait::failPath().
   *
   * @param string $path
   * @param string $read_write
   * @param string $operation_description
   * @param \ErrorException|NULL $error_exception
   */
  protected abstract function failPath($path, $read_write, $operation_description, \ErrorException $error_exception = NULL);

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

  // TODO: WriteAdapterInterface exceptions contract
  public function filePutContents($filename, $data, $lock_if_able = TRUE) {
    $flags = ($lock_if_able && $this->getStreamContext()->getScheme() === 'file://'
      ? LOCK_EX : 0);

    try {
      return file_put_contents(
        $this->alterPathForStreamWrapper($filename),
        $data,
        $flags,
        $this->getStreamContext()->getContext()
      );
    } catch (\ErrorException $e) {
      throw new FileException($e->getMessage(), $filename, 0, $e);
    }
  }

  public function mkdir($path) {
    if (! mkdir(
      $this->alterPathForStreamWrapper($path),
      0755, // TODO: something fancier for mode, caution: umask
      FALSE,
      $this->getStreamContext()->getContext()
    )) {
      throw new \UnexpectedValueException();
    }
  }

  public function rename($old_name, $new_name) {
    $old_name = $this->alterPathForStreamWrapper($old_name);
    $new_name = $this->alterPathForStreamWrapper($new_name);
    try {
      $result = rename(
        $old_name,
        $new_name,
        $this->getStreamContext()->getContext());
    } catch (\ErrorException $e) {
      // We are, unfortunately, not in a position to know which path is broken.
      $this->failPath($old_name, 'w', sprintf('Renaming %s to %s', $old_name, $new_name), $e);
    }
    if (! $result) {
      $this->failPath($old_name, 'w', sprintf('Renaming %s to %s', $old_name, $new_name));
    }
  }

  public function rmDir($path) {
    $path = $this->alterPathForStreamWrapper($path);

    try {
      $result = rmdir(
        $path,
        $this->getStreamContext()->getContext()
      );
    } catch (\ErrorException $e) {
      $this->failPath($path, 'w', sprintf('Removing directory at "%s". If no other reason is given, perhaps this is not an empty directory?', $path), $e);
    }
    if (! $result) {
      $this->failPath($path, 'w', sprintf('Removing directory at "%s". If no other reason is given, perhaps this is not an empty directory?', $path));
    }
  }

  public function unlink($filename) {
    $filename = $this->alterPathForStreamWrapper($filename);

    try {
      $result = unlink(
        $filename,
        $this->getStreamContext()->getContext()
      );
    } catch (\ErrorException $e) {
      $this->failPath($filename, 'w', sprintf('Removing file at "%s". If no other reason is given, perhaps this is a directory?', $filename), $e);
    }
    if (! $result) {
      $this->failPath($filename, 'w', sprintf('Removing file at "%s". If no other reason is given, perhaps this is a directory?', $filename));
    }
  }

}
