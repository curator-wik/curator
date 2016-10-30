<?php


namespace Curator\Tests\Unit\FSAccess\Mocks;


use Curator\FSAccess\FileException;
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

  public function rename($old_name, $new_name) {
    if ($this->_isInProjectRoot($old_name)) {
      $old_name = $this->_realPath($old_name, NULL, TRUE, FALSE); // throws if no $old_name.
      $old_name_rel = $this->_toRelativePath($old_name);

      if ($this->_isInProjectRoot($new_name)) {
        if ($this->_pathExists($new_name, TRUE, FALSE)) {
          $this->unlink($new_name);
        } else {
          if (! $this->_isDir(dirname($new_name))) {
            throw new FileNotFoundException('Destination directory does not exist.', $new_name);
          }
        }
      }

      if (array_key_exists($old_name_rel, $this->contents->symlinks)) {
        $manip_ref =& $this->contents->symlinks;
        $manip_style = 'key';
        $manip_data = $manip_ref[$old_name_rel];
      } else if ($this->_isDir($old_name)) {
        $manip_ref =& $this->contents->directories;
        $manip_style = 'value';
      } else if ($this->_isFile($old_name)) {
        $manip_ref =& $this->contents->files;
        $manip_style = 'key';
        $manip_data = $manip_ref[$old_name_rel];
      } else if ($this->_isSpecial($old_name)) {
        $manip_ref =& $this->contents->specials;
        $manip_style = 'value';
      } else {
        throw new \LogicException('Mock filesystem path ' . $old_name . ' is a filesystem object per _realPath, but was not found in any type array');
      }

      $this->unlink($old_name);
      if ($this->_isInProjectRoot($new_name)) {
        switch ($manip_style) {
          case 'value':
            $manip_ref[] = $this->_toRelativePath($new_name);
            break;
          case 'key':
            $manip_ref[$this->_toRelativePath($new_name)] = $manip_data;
            break;
          default:
            throw new \LogicException('Unknown internal rename manipulation type');
        }
      }
    }
  }

  protected function _do_rm($filename, $type) {
    if ($this->_isInProjectRoot($filename)) {
      $filename = $this->_realPath($filename, NULL, TRUE, FALSE); // throws if no $filename.
      $filename_rel = $this->_toRelativePath($filename);

      if (array_key_exists($filename_rel, $this->contents->symlinks)) {
        if ($type == 'directory') {
          throw new FileException('Is not a directory.', $filename);
        }
        $manip_ref =& $this->contents->symlinks;
        $manip_style = 'key';
      } else if ($this->_isDir($filename)) {
        if ($type == 'file') {
          throw new FileException('Is a directory.', $filename);
        }
        $manip_ref =& $this->contents->directories;
        $manip_style = 'value';
      } else if ($this->_isFile($filename)) {
        if ($type == 'directory') {
          throw new FileException('Is not a directory.', $filename);
        }
        $manip_ref =& $this->contents->files;
        $manip_style = 'key';
      } else if ($this->_isSpecial($filename)) {
        if ($type == 'directory') {
          throw new FileException('Is not a directory.', $filename);
        }
        $manip_ref =& $this->contents->specials;
        $manip_style = 'value';
      } else {
         throw new \LogicException('Mock filesystem path ' . $filename . ' is a filesystem object per _realPath, but was not found in any type array');
      }

      $copy = [];
      foreach ($manip_ref as $key => $value) {
        if ($manip_style == 'value') {
          if ($value !== $filename_rel) {
            $copy[$key] = $value;
          }
        } else {
          if ($key !== $filename_rel) {
            $copy[$key] = $value;
          }
        }
      }
      $manip_ref = $copy;
    }
  }


  public function unlink($filename) {
    $this->_do_rm($filename, 'file');
  }

  public function rmDir($path) {
    $this->_do_rm($path, 'directory');
  }

  protected function _rm($path) {
    if ($this->_isDir($path)) {
      $this->rmDir($path);
    } else {
      $this->unlink($path);
    }
  }

  public function setMockedCwd($cwd) {
    $this->cwd = $cwd;
  }

  public function getCwd() {
    return $this->cwd;
  }
}
