<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\WriteAdapterInterface;

class WriteAdapterMock implements WriteAdapterInterface {
  use MockedFilesystemTrait;

  /**
   * @var string cwd
   */
  protected $cwd;

  public function __construct($project_root) {
    $this->_setProjectRoot($project_root);
  }

  public function ls($directory) {
    return $this->_ls($directory);
  }

  public function filePutContents($filename, $data, $lock_if_able = TRUE) {
    if (! self::pathIsAbsolute($filename)) {
      throw new \LogicException('Relative paths not allowed');
    }

    if ($this->_isDir(dirname($filename))) {
      $dir = $this->_realPath(dirname($filename), NULL);
      $new_path = $this->_toRelativePath(
        $this->simplifyPath($dir . '/' . basename($filename)));
      $this->contents->files[$new_path] = $data;
      return strlen($data);
    } else {
      throw new FileNotFoundException(sprintf('Missing directories to create "%s"', $filename));
    }
  }

  public function mkdir($path) {
    if (! self::pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }

    if ($this->_isInProjectRoot($path)) {
      if ($this->_isDir(dirname($path))) {
        $dir = $this->_realPath(dirname($path), NULL);
        $new_path = $this->simplifyPath($dir . '/' . basename($path));
        if ($this->_pathExists($new_path, FALSE)) {
          throw new FileExistsException($path, 0);
        }
        $this->contents->directories[] = $this->_toRelativePath($new_path);
      }
      else {
        if ($this->_pathExists(dirname($path))) {
          throw new FileExistsException($path, 1);
        } else {
          throw new FileNotFoundException(sprintf('Missing directories to create "%s"', $path));
        }
      }
    }
  }

  public function setMockedCwd($cwd) {
    $this->cwd = $cwd;
  }

  public function getCwd() {
    return $this->cwd;
  }
}
