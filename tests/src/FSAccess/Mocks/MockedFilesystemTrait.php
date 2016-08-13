<?php


namespace Curator\Tests\FSAccess\Mocks;


use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParserInterface;
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

  protected function getPathParser() {
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
   * @return \Curator\Tests\FSAccess\Mocks\MockedFilesystemContents
   *   The underlying filesystem contents data. Probably shouldn't be used for
   *   much other than as a source for assertion expected values.
   */
  public function getFilesystemContents() {
    return $this->contents;
  }

  protected function _realPath($path, $relative_to, $throwOnMissingPath = TRUE) {
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
        $this->projRoot . '/' . $this->_resolveSymlinks($path));
      if ($throwOnMissingPath
        && $this->_isInProjectRoot($converted_path, TRUE)
        && ! $this->_pathExists($converted_path, FALSE)) {
        throw new FileNotFoundException($path);
      } else {
        return $converted_path;
      }
    } else {
      return $path;
    }
  }

  protected function _resolveSymlinks($path) {
    if (! $this->_isInProjectRoot($path, TRUE)) {
      return $path;
    }

    if ($this->pathIsAbsolute($path)) {
      $path = $this->_toRelativePath($path);
    }

    $location = $this->simplifyPath($path);
    $nonsymlink_elements = [];
    $path_elements = explode('/', $location);
    while(count($path_elements)) {
      $link_test = implode('/', $path_elements);
      if (array_key_exists($link_test, $this->contents->symlinks)) {
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
    if ($this->_isSpecial($path, FALSE)) {
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
      return strncmp($this->simplifyPath($path), $this->projRoot, strlen($this->projRoot)) === 0;
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

  //<editor-fold desc="PathParserInterface">
  // We implement these because the adapters that use this trait need them.
  /**
   * @inheritDoc
   */
  function getAbsolutePrefix($path) {
    return $this->getPathParser()->getAbsolutePrefix($path);
  }

  public function pathIsAbsolute($path) {
    return $this->getPathParser()->pathIsAbsolute($path);
  }

  /**
   * @inheritDoc
   */
  function getDirectorySeparators() {
    return $this->getPathParser()->getDirectorySeparators();
  }

  /**
   * @inheritDoc
   */
  function translate($path, PathParserInterface $translate_to) {
    return $this->getPathParser()->translate($path, $translate_to);
  }
  //</editor-fold>
}
