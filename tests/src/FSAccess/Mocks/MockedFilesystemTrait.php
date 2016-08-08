<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileException;
use Curator\FSAccess\PathSimplificationTrait;

trait MockedFilesystemTrait {
  use PathSimplificationTrait;

  /**
   * @var MockedFilesystemContents $contents
   */
  protected $contents;

  /**
   * @var string $cwd
   *   The current working directory
   */
  protected $cwd = '/test/mock/default';
  protected $proj_root = '/';

  public function getAdapterName() {
    return 'test mock';
  }

  protected function _setProjectRoot($path) {
    if (! static::_pathIsAbsolute($path)) {
      throw new \InvalidArgumentException('Project root path must be absolute');
    }

    $this->proj_root = $this->simplifyPath($path);
  }

  public function setFilesystemContents(MockedFilesystemContents $contents) {
    $this->contents = $contents;
  }

  protected function _chdir($path) {
    $this->cwd = $path;
    return TRUE;
  }

  protected function _realPath($path, $throwOnMissingPath = TRUE) {
    $path = $this->simplifyPath($path);
    if ($this->_isInProjectRoot($path)) {
      if ($throwOnMissingPath && ! $this->_pathExists($path, FALSE)) {
        throw new FileException(sprintf('Path "%s" does not exist in the mocked fs.', $path));
      }

      if (! static::_pathIsAbsolute($path)) {
        // We can't lose track of what $path is truly relative to
        $path = $this->simplifyPath($this->cwd . '/' . $path);
      }

      // Transform to path findable in MockedFilesystemContents
      $path = $this->_toRelativePath($path);

      // This quick hack for symlink resolution won't work with symlinks
      // placed below the project root directory.
      if (array_key_exists($path, $this->contents->symlinks)) {
        $readlink = $this->contents->symlinks[$path];
        switch ($readlink) {
          case '../':
            $newpath = dirname($path);
            break;
          default:
            $newpath = $readlink;
            break;
        }
        return $this->simplifyPath($this->proj_root . '/' . $newpath);
      } else {
        return $this->simplifyPath($this->proj_root . '/' . $path);
      }
    } else {
      return $path;
    }
  }

  /**
   * Transforms absolute paths within the project root to relative paths from
   * the project root.
   *
   * @param string $path
   * @return string
   * @throws \InvalidArgumentException
   *   If the path is absolute but outside the project root.
   * @throws \LogicException
   */
  protected function _toRelativePath($path) {
    if (! static::_pathIsAbsolute($path)) {
      return $path;
    } else if (! $this->_isInProjectRoot($path)) {
      throw new \InvalidArgumentException('MockedFilesystemTrait::_toRelativePath invoked on an absolute path outside the project root');
    } else {
      $path = $this->simplifyPath($path);
      // We expect the $path to begin with the project root in this case
      if (strncmp($path, $this->proj_root, strlen($this->proj_root)) !== 0) {
        throw new \LogicException('Failed expectation that path matches project root');
      }
      $proj_root = $this->proj_root;
      if (substr($proj_root, -1) !== '/') {
        $proj_root .= DIRECTORY_SEPARATOR;
      }
      $rel_path = substr($path, strlen($proj_root));

      return empty($rel_path) ? '.' : $rel_path;
    }
  }

  protected static function _pathIsAbsolute($path) {
    return strncmp($path, '/', 1) === 0;
  }

  protected function _isInProjectRoot($path) {
    if (static::_pathIsAbsolute($path)) {
      return strncmp($this->simplifyPath($path), $this->proj_root, strlen($this->proj_root)) === 0;
    } else if (strncmp($path, './../', 5) === 0) {
      return FALSE;
    } else {
      // Relative paths are within the project root when the cwd is
      return strncmp($this->cwd, $this->proj_root, strlen($this->proj_root)) === 0;
    }
  }

  protected function _pathExists($path, $translate_real = TRUE) {
    if ($translate_real) {
      $path = $this->_realPath($path, FALSE);
    }

    if ($this->_isDir($path, FALSE)) {
      return TRUE;
    }
    if ($this->_isFile($path, FALSE)) {
      return TRUE;
    }
    if (array_key_exists($path, $this->contents->symlinks) && basename($path) != 'broken.link') {
      return TRUE;
    }
    if (in_array($path, $this->contents->specials)) {
      return TRUE;
    }

    if ($path == 'inaccessible') {
      throw new FileException();
    }

    return FALSE;
  }

  protected function _isDir($path, $translate_real = TRUE) {
    // Special path '.' is always a directory
    if ($path == '.') {
      return TRUE;
    }

    if ($translate_real) {
      $path = $this->_realPath($path, FALSE);
    }

    if (self::_isInProjectRoot($path)) {
      $path = self::_toRelativePath($path);
      return $path == '.' || in_array($path, $this->contents->directories);
    } else {
      return FALSE;
    }
  }

  protected function _isFile($path, $translate_real = TRUE) {
    if ($translate_real) {
      $path = $this->_realPath($path, FALSE);
    }

    if (self::_isInProjectRoot($path)) {
      $path = self::_toRelativePath($path);
      return array_key_exists($path, $this->contents->files);
    } else {
      return FALSE;
    }
  }
}
