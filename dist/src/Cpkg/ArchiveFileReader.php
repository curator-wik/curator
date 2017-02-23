<?php


namespace Curator\Cpkg;


use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ArchiveFileReader {

  /**
   * @var \PharData $phar
   */
  protected $phar;

  /**
   * ArchiveFileReader constructor.
   *
   * @param string $archive_path
   */
  public function __construct($archive_path) {
    $this->phar = new \PharData($archive_path);
  }

  /**
   * @param string $path
   *   A path within the archive file.
   *
   * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
   *   When $path is not a regular file in the archive.
   *
   * @return string
   */
  public function getContent($path) {
    try {
      /**
       * @var \PharFileInfo $finfo
       */
      $finfo = $this->phar[$path];
    } catch (\BadMethodCallException $e) {
      throw new FileNotFoundException($path);
    }

    if (! $finfo->isFile()) {
      throw new FileNotFoundException($path);
    }

    // Work around a segfault in php 5.4 when getting empty file content.
    return $finfo->getSize() === 0 ? '' : $finfo->getContent();
  }

  public function tryGetContent($path, $default = '') {
    try {
      return $this->getContent($path);
    } catch (FileNotFoundException $e) {
      return $default;
    }
  }
}
