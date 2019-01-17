<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;

/**
 * Class ArchiveFileReader
 *   At present, this class basically wraps PharData methods. It exists because
 *   I don't particularly like the storing of cpkg's in /tmp while they are
 *   getting extracted. This class acts as an abstraction layer to a future
 *   alternative means of handling archive file I/O.
 */
class ArchiveFileReader implements CpkgReaderPrimitivesInterface {

  /**
   * @var \PharData[]
   */
  protected static $underlying_readers_by_path = [];

  /**
   * @var int[]
   */
  protected static $underlying_paths_refcounters = [];

  /**
   * @var \PharData $phar
   */
  protected $phar;

  /**
   * @var string $archive_path
   */
  protected $archive_path;

  /**
   * ArchiveFileReader constructor.
   *
   * @param string $archive_path
   *   Path on filesystem to the archive.
   */
  public function __construct($archive_path) {
    if (array_key_exists($archive_path, self::$underlying_readers_by_path)) {
      self::$underlying_paths_refcounters[$archive_path]++;
    } else {
      self::$underlying_readers_by_path[$archive_path] = new \PharData($archive_path);
      if (self::$underlying_readers_by_path[$archive_path]->count() === 0) {
        throw new FileException(sprintf('Archive at %s does not exist or is empty.', $archive_path));
      }
      // If that doesn't throw, set counter
      self::$underlying_paths_refcounters[$archive_path] = 1;
    }

    $this->archive_path = $archive_path;
    $this->phar = self::$underlying_readers_by_path[$archive_path];
  }

  /**
   * @return string
   *   The path passed to the constructor that references the archive file.
   */
  public function getArchivePath() {
    return $this->archive_path;
  }

  /**
   * @param string $path
   *   A path within the archive file.
   *
   * @throws \Curator\FSAccess\FileNotFoundException
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

  public function isFile($path) {
    try {
      /**
       * @var \PharFileInfo $finfo
       */
      $finfo = $this->phar[$path];
    } catch (\BadMethodCallException $e) {
      return FALSE;
    }
    return $finfo->isFile();
  }

  public function isDir($path) {
    try {
      /**
       * @var \PharFileInfo $finfo
       */
      $finfo = $this->phar[$path];
    } catch (\BadMethodCallException $e) {
      return FALSE;
    }
    return $finfo->isDir();
  }

  /**
   * Gets an iterator over all files and directories below the given path.
   *
   * // TODO: develop our own recursive iterator that efficiently implements
   * // \SeekableIterator given an index of every n'th path. This could improve
   * // batch runner incarnation startup time on very large archives.
   *
   * @param string $internal_path
   *   The path within the archive to iterate all descendants of.
   *   The empty string will result in an iterator over the entire contents.
   * @return \Iterator
   */
  public function getRecursiveFileIterator($internal_path = '') {
    $location = sprintf('phar://%s/%s', $this->archive_path, $internal_path);
    return new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($location),
      \RecursiveIteratorIterator::SELF_FIRST
    );
  }

  public function __destruct() {
    self::$underlying_paths_refcounters[$this->archive_path]--;
    if (self::$underlying_paths_refcounters[$this->archive_path] === 0) {
      unset (self::$underlying_readers_by_path[$this->archive_path]);
      unset (self::$underlying_paths_refcounters[$this->archive_path]);
    }
  }
}
