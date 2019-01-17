<?php


namespace Curator\Cpkg;

use Curator\FSAccess\FSAccessManager;
use Curator\FSAccess\ReadAdapterInterface;
use Curator\FSAccess\WriteAdapterInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Class FSAccessReader
 * An alternative CpkgReader implementation that adapts a file-based
 * FSAccessManager to the interface.
 *
 * Used when reading a rollback capture.
 *
 * Not a service: multiple instances are created, etc.
 */
class FSAccessReader implements CpkgReaderPrimitivesInterface {

  protected static $underlying_managers_by_path = [];

  protected static $underlying_managers_refcounters = [];

  protected $cpkg_path;

  /**
   * @var \Curator\FSAccess\FSAccessInterface $fs_access
   */
  protected $fs_access;

  public function __construct(
    $cpkg_path,
    ReadAdapterInterface $fsAccessReader,
    WriteAdapterInterface $fsAccessWriter
  ) {
    $this->cpkg_path = $cpkg_path;
    if (!empty(self::$underlying_managers_by_path[$cpkg_path])) {
      self::$underlying_managers_refcounters[$cpkg_path]++;
    } else {
      if (! is_dir($cpkg_path)) {
        throw new FileException('Cpkg not found at ' . $cpkg_path);
      }

      $m = new FSAccessManager($fsAccessReader, $fsAccessWriter);
      $m->setWorkingPath($cpkg_path);
      self::$underlying_managers_by_path[$cpkg_path] = $m;
      self::$underlying_managers_refcounters[$cpkg_path] = 1;
    }

    $this->fs_access = self::$underlying_managers_by_path[$cpkg_path];
  }

  public function getArchivePath() {
    return $this->cpkg_path;
  }

  public function getContent($path) {
    return $this->fs_access->fileGetContents($path);
  }

  public function tryGetContent($path, $default = '') {
    try {
      return $this->getContent($path);
    } catch (\Curator\FSAccess\FileException $e) {
      return $default;
    }
  }

  public function isFile($path) {
    return $this->fs_access->isFile($path);
  }

  public function isDir($path) {
    return $this->fs_access->isDir($path);
  }

  public function getRecursiveFileIterator($internal_path = '') {
    $location = sprintf('%s%s',
      $this->fs_access->ensureTerminatingSeparator($this->getArchivePath()),
      $internal_path);
    return new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($location),
      \RecursiveIteratorIterator::SELF_FIRST
    );
  }

  public function __destruct() {
    self::$underlying_managers_refcounters[$this->cpkg_path]--;
    if (self::$underlying_managers_refcounters[$this->cpkg_path] === 0) {
      unset(self::$underlying_managers_by_path[$this->cpkg_path]);
      unset(self::$underlying_managers_refcounters[$this->cpkg_path]);
    }
  }
}