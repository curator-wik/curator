<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\WriteAdapterInterface;

class WriteAdapterMock implements WriteAdapterInterface {
  use MockedFilesystemTrait;

  public function __construct($project_root) {
    $this->_setProjectRoot($project_root);
  }

  public function filePutContents($filename, $data, $lock_if_able = TRUE) {
    if (! self::_pathIsAbsolute($filename)) {
      throw new \LogicException('Relative paths not allowed');
    }

    if ($this->_isDir(dirname($filename))) {
      $dir = $this->_realPath(dirname($filename));
      $new_path = $this->_toRelativePath(
        $this->simplifyPath($dir . '/' . basename($filename)));
      $this->contents->files[$new_path] = $data;
      return strlen($data);
    } else {
      throw new FileNotFoundException(sprintf('Missing directories to create "%s"', $filename));
    }
  }

  public function mkdir($path) {
    if (! self::_pathIsAbsolute($path)) {
      throw new \LogicException('Relative paths not allowed');
    }

    // Comply with WriteAdapterInterface contract for exception throwing.
    // Probe all children for non-directory objects.
    if ($this->_isInProjectRoot($path)) {
      $path_elements = explode('/', $this->_toRelativePath(dirname($this->_realPath($path))));
      $test_path = array_shift($path_elements);
      do {
        if ($this->_pathExists($test_path)) {
          if (!$this->_isDir($test_path)) {
            throw new FileExistsException(sprintf('Cannot create directory at "%s": object in path at "%s" is not a directory.', $path, $test_path));
          }
        }
        $test_path .= '/' . array_shift($path_elements);
      } while (count($path_elements));

      if ($this->_isDir(dirname($path))) {
        $dir = $this->_realPath(dirname($path));
        $new_path = $this->_toRelativePath($this->simplifyPath($dir . '/' . basename($path)));
        if ($this->_pathExists($new_path)) {
          throw new FileExistsException(sprintf('Cannot create directory at "%s": object already exists here.', $path));
        }
        $this->contents->directories[] = $new_path;
      }
      else {
        throw new FileNotFoundException(sprintf('Missing directories to create "%s"', $path));
      }
    }
  }
}
