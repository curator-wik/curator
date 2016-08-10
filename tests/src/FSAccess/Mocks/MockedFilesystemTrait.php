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
   * @var string $proj_root
   *   The location in the mocked filesystem where our simulated contents start.
   */
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

  protected function _realPath($path, $relative_to, $throwOnMissingPath = TRUE) {
    if (static::_pathIsAbsolute($path)) {
      $path = $this->simplifyPath($path);
    } else if (empty($relative_to)) {
      throw new \LogicException('Cannot resolve canonical absolute path given a relative path and no $relative_to');
    } else {
      $path = $this->simplifyPath($relative_to . '/' . $path);
    }

    if ($this->_isInProjectRoot($path)) {
      if ($throwOnMissingPath && ! $this->_pathExists($path, FALSE)) {
        throw new FileException(sprintf('Path "%s" does not exist in the mocked fs.', $path));
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

  protected function _pathExists($path, $translate_real = TRUE) {
    if ($translate_real) {
      $path = $this->_realPath($path, NULL, FALSE);
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

  /**
   * Tests whether a path is within the tree of the mocked filesystem
   * that we are simulating the contents of. This helps the mocked filesystem's
   * other methods know how to respond to a given path.
   *
   * In other words, the mocked filesystem supports "mounting" the mocked
   * contents to pretend they begin at some location other than '/', say
   * /foo/bar. This tests whether the given path is part of the mocked contents:
   * /foo/bar/test would be in that case, but not /baz.
   *
   * @param string $path
   *
   * @return bool
   */
  protected function _isInProjectRoot($path) {
    if (! static::_pathIsAbsolute($path)) {
      throw new \LogicException('API contract violation: relative paths not allowed by the low-level read/write adapters.');
    } else {
      return strncmp($this->simplifyPath($path), $this->proj_root, strlen($this->proj_root)) === 0;
    }
  }

  protected function _isDir($path, $translate_real = TRUE) {
    // Special path '.' is always a directory
    if ($path == '.') {
      return TRUE;
    }

    if ($translate_real) {
      $path = $this->_realPath($path, NULL, FALSE);
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
      $path = $this->_realPath($path, NULL, FALSE);
    }

    if (self::_isInProjectRoot($path)) {
      $path = self::_toRelativePath($path);
      return array_key_exists($path, $this->contents->files);
    } else {
      return FALSE;
    }
  }
}
