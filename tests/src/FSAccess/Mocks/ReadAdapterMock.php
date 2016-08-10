<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\ReadAdapterInterface;

class ReadAdapterMock implements ReadAdapterInterface {
  use MockedFilesystemTrait;

  public function __construct($project_root) {
    $this->_setProjectRoot($project_root);
  }

  public function realPath($path, $relative_to = NULL) {
    return $this->_realPath($path, $relative_to);
  }

  public function pathIsAbsolute($path) {
    return static::_pathIsAbsolute($path);
  }

  public function pathExists($path) {
    if (! self::_pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_pathExists($path);
  }

  public function isDir($path) {
    if (! self::_pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_isDir($path);
  }

  public function isFile($path) {
    if (! self::_pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_isFile($path);
  }

  public function fileGetContents($filename) {
    if (! self::_pathIsAbsolute($filename)) {
      throw new \LogicException('Relative paths not allowed');
    }
    if ($this->_isFile($filename)) {
      $path = $this->_toRelativePath($this->_realPath($filename));
      if (! array_key_exists($path, $this->contents->files)) {
        throw new \LogicException('File said to exist does not');
      }
      return $this->contents->files[$path];
    } else if ($this->_pathExists($filename)) {
      throw new FileException(sprintf('"%s" is not a file', $filename));
    } else {
      throw new FileNotFoundException(sprintf('File "%s" does not exist.', $filename));
    }
  }

}
