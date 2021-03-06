<?php


namespace Curator\Tests\Unit\FSAccess\Mocks;


use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathSimplificationTrait;

trait MockedFilesystemTrait {
  use PathSimplificationTrait;

  /**
   * @var MockedFilesystemContents $contents
   */
  protected $contents;

  /**
   * @var string $projRoot
   *   The location in the mocked filesystem where our simulated contents start.
   */
  protected $projRoot = '/';

  /**
   * @var PosixPathParser $pathParser
   *   The mocked filesystem uses a PosixPathParser.
   */
  protected $pathParser;

  public function getAdapterName() {
    return 'test mock';
  }

  public function getPathParser() {
    if (empty($this->pathParser)) {
      $this->pathParser = new PosixPathParser();
    }
    return $this->pathParser;
  }

  protected function _setProjectRoot($path) {
    if (! $this->pathIsAbsolute($path)) {
      throw new \InvalidArgumentException('Project root path must be absolute');
    }

    $this->projRoot = $this->simplifyPath($path);
  }

  public function setFilesystemContents(MockedFilesystemContents $contents) {
    $this->contents = $contents;
  }

  /**
   * @return \Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents
   *   The underlying filesystem contents data.
   */
  public function getFilesystemContents() {
    return $this->contents;
  }

  protected function _realPath($path, $relative_to = NULL, $throwOnMissingPath = TRUE, $resolve_symlink = TRUE) {
    if ($this->pathIsAbsolute($path)) {
      $path = $this->simplifyPath($path);
    } else if (empty($relative_to)) {
      throw new \LogicException('Cannot resolve canonical absolute path given a relative path and no $relative_to');
    } else {
      $path = $this->simplifyPath($relative_to . '/' . $path);
    }

    if ($this->_isInProjectRoot($path)) {
      // Transform to path findable in MockedFilesystemContents
      $path = $this->_toRelativePath($path);

      $converted_path = $this->simplifyPath(
        $this->projRoot . '/' . $this->_resolveSymlinks($path, $resolve_symlink));
      if ($throwOnMissingPath
        && $this->_isInProjectRoot($converted_path, TRUE)
        && ! $this->_pathExists($converted_path, FALSE, $resolve_symlink)) {
        throw new FileNotFoundException($path);
      } else {
        return $converted_path;
      }
    } else {
      return $path;
    }
  }

  protected function _resolveSymlinks($path, $resolve_leaf = TRUE) {
    $skip_resolve = !$resolve_leaf;
    if (! $this->_isInProjectRoot($path, TRUE)) {
      return $path;
    }

    if ($this->pathIsAbsolute($path)) {
      $path = $this->_toRelativePath($path);
    }

    $location = $this->simplifyPath($path);
    $nonsymlink_elements = [];
    $path_elements = explode('/', $location);
    while (count($path_elements)) {
      $link_test = implode('/', $path_elements);
      if (!$skip_resolve && array_key_exists($link_test, $this->contents->symlinks)) {
        $readlink = $this->contents->symlinks[$link_test];
        if (strncmp($readlink, './', 2) == 0 || strncmp($readlink, '../', 3) == 0) {
          $resolved_path = $this->simplifyPath(dirname($link_test) . '/' . $readlink . '/' . implode('/', $nonsymlink_elements));
        } else {
          $resolved_path = $this->contents->symlinks[$link_test] . '/' . implode('/', $nonsymlink_elements);
        }

        return $this->_resolveSymlinks($resolved_path);
      } else {
        array_unshift($nonsymlink_elements, array_pop($path_elements));
      }
      $skip_resolve = FALSE;
    }
    return $path;
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
    if (! $this->pathIsAbsolute($path)) {
      return $path;
    } else if (! $this->_isInProjectRoot($path)) {
      throw new \InvalidArgumentException('MockedFilesystemTrait::_toRelativePath invoked on an absolute path outside the project root');
    } else {
      $path = $this->simplifyPath($path);
      // We expect the $path to begin with the project root in this case
      if (strncmp($path, $this->projRoot, strlen($this->projRoot)) !== 0) {
        throw new \LogicException('Failed expectation that path matches project root');
      }
      $proj_root = $this->projRoot;
      if (substr($proj_root, -1) !== '/') {
        $proj_root .= DIRECTORY_SEPARATOR;
      }
      $rel_path = substr($path, strlen($proj_root));

      return empty($rel_path) ? '.' : $rel_path;
    }
  }

  /**
   * @param string $path
   * @param bool $translate_real
   * @param bool $resolve_symlink
   *   If TRUE, any symlinks found in $path must be valid. If FALSE, it is
   *   sufficient for the symlinks themselves to be present, valid or broken.
   * @return bool
   */
  protected function _pathExists($path, $translate_real = TRUE, $resolve_symlink = TRUE) {
    if ($translate_real) {
      $path = $this->_realPath($path, NULL, FALSE, $resolve_symlink);
    }

    if ($resolve_symlink === FALSE && array_key_exists($this->_toRelativePath($path), $this->contents->symlinks)) {
      return TRUE;
    }
    if ($this->_isDir($path, FALSE)) {
      return TRUE;
    }
    if ($this->_isFile($path, FALSE)) {
      return TRUE;
    }
    if ($this->_isSpecial($path, FALSE)) {
      return TRUE;
    }

    if ($path == 'inaccessible') {
      throw new FileException('Permission denied.');
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
   * @param bool $ignore_contract
   *
   * @return bool
   */
  protected function _isInProjectRoot($path, $ignore_contract = FALSE) {
    if (! $this->pathIsAbsolute($path)) {
      if ($ignore_contract) {
        return strncmp($path, './../', 5) !== 0;
      } else {
        throw new \LogicException('API contract violation: relative paths not allowed by the low-level read/write adapters.');
      }
    } else {
      $simplified_path = $this->simplifyPath($path);
      $nextPathChar = substr($simplified_path, strlen($this->projRoot), 1);
      return strncmp($simplified_path, $this->projRoot, strlen($this->projRoot)) === 0 &&
        (substr($this->projRoot, -1, 1) === '/' || $simplified_path === $this->projRoot || $nextPathChar === '/');
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

  protected function _isSpecial($path, $translate_real = TRUE) {
    if ($translate_real) {
      $path = $this->_realPath($path, NULL, FALSE);
    }

    if (self::_isInProjectRoot($path)) {
      $path = self::_toRelativePath($path);
      return in_array($path, $this->contents->specials);
    } else {
      return FALSE;
    }
  }

  protected function _ls($directory) {
    if ($this->_isInProjectRoot($directory)) {
      $directory = self::_toRelativePath($directory);
      // A $directory == project root would be translated to '.'
      if ($directory === '.') {
        $directory = '';
      }

      // Now for the "fun" part...MockedFilesystemContents is so not optimized
      // for ls().
      $items = [];
      $items = array_merge($items, $this->ls_content_type('directories', $directory));
      $items = array_merge($items, $this->ls_content_type('files', $directory));
      $items = array_merge($items, $this->ls_content_type('symlinks', $directory));
      $items = array_merge($items, $this->ls_content_type('specials', $directory));
      sort($items);
      return array_unique($items);
    } else {
      // If it's a parent of the project root, the tests will need to see the
      // next child towards the project root in the listing.
      if (substr($directory, -1) !== DIRECTORY_SEPARATOR) {
        $directory .= DIRECTORY_SEPARATOR;
      }
      if (strncmp($this->projRoot, $directory, strlen($directory)) === 0) {
        $item = substr($this->projRoot, strlen($directory));
        return array(explode(DIRECTORY_SEPARATOR, $item)[0]);
      }
    }
    return array();
  }

  protected function ls_content_type($type, $directory) {
    $items = [];
    foreach ($this->getFilesystemContents()->{$type} as $key => $value) {
      if (in_array($type, array('directories', 'specials'))) {
        $item = $value;
      } else {
        $item = $key;
      }
      if (strncmp($directory, $item, strlen($directory)) === 0) {
        // Grab just the part after the requested $directory until the next
        $item = substr($item, strlen($directory));
        if (strncmp($item, DIRECTORY_SEPARATOR, strlen(DIRECTORY_SEPARATOR)) === 0) {
          $item = substr($item, strlen(DIRECTORY_SEPARATOR));
        }
        $item = explode(DIRECTORY_SEPARATOR, $item)[0];
        if (strlen($item)) {
          $items[] = $item;
        }
      }
    }
    return $items;
  }

  protected function pathIsAbsolute($path) {
    return $this->getPathParser()->pathIsAbsolute($path);
  }
}
