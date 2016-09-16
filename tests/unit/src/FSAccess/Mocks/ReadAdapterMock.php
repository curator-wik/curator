<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\ReadAdapterInterface;

class ReadAdapterMock implements ReadAdapterInterface {
  use MockedFilesystemTrait;

  public function __construct($project_root) {
    $this->_setProjectRoot($project_root);
  }

  public function ls($directory) {
    return $this->_ls($directory);
  }

  public function realPath($path, $relative_to = NULL) {
    return $this->_realPath($path, $relative_to);
  }

  public function pathExists($path) {
    if (! self::pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_pathExists($path);
  }

  public function isDir($path) {
    if (! self::pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_isDir($path);
  }

  public function isFile($path) {
    if (! self::pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }
    return $this->_isFile($path);
  }

  public function fileGetContents($filename) {
    if (! self::pathIsAbsolute($filename)) {
      throw new \LogicException('Relative paths not allowed');
    }
    if ($this->_isFile($filename)) {
      $path = $this->_toRelativePath($this->_realPath($filename, NULL));
      if (! array_key_exists($path, $this->contents->files)) {
        throw new \LogicException('File said to exist does not');
      }
      return $this->contents->files[$path];
    } else if ($this->_pathExists($filename)) {
      throw new FileExistsException(sprintf('"%s" is not a file', $filename));
    } else {
      throw new FileNotFoundException(sprintf('File "%s" does not exist.', $filename));
    }
  }



}
