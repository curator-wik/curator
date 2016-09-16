<?php

namespace Curator\FSAccess;

use Curator\Util\ErrorHandling;

trait WriteAdapterStreamWrapperTrait
{
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
      return ErrorHandling::withErrorException('file_put_contents',
        E_ALL & ~E_NOTICE,
        array(
          $this->alterPathForStreamWrapper($filename),
          $data,
          $flags,
          $this->getStreamContext()->getContext()
        )
      );
    } catch (\ErrorException $e) {
      throw new FileException($e->getMessage(), 0, $e);
    }
  }

  public function mkdir($path) {
    return mkdir(
      $this->alterPathForStreamWrapper($path),
      0755, // TODO: something fancier for mode
      FALSE,
      $this->getStreamContext()->getContext()
    );
  }

  public function ls($directory) {
    try {
      $dh = ErrorHandling::withErrorException('opendir',
        E_ALL & ~E_NOTICE,
        array(
          $directory,
          $this->getStreamContext()->getContext()
        )
      );

      $result = [];
      while (FALSE !== ($name = readdir($dh))) {
        if ($name === '.' || $name === '..') {
          continue;
        }
        $result[] = $name;
      }
      sort($result, SORT_STRING);
      return $result;
    } catch (\ErrorException $e) {
      throw new FileException($e->getMessage(), 0, $e);
    }
  }

}
