<?php

namespace Curator\FSAccess\PathParser;

/**
 * Interface PathInfoInterface
 *
 */
interface PathParserInterface {

  /**
   * Determines if the $path is considered absolute.
   *
   * @param string $path
   * @return bool
   *   TRUE if the path is absolute, or FALSE otherwise.
   */
  function pathIsAbsolute($path);

  /**
   * Returns the character(s) used to separate directories in path strings.
   *
   * @return string[]
   *   The first element of the array should be the preferred character(s) to
   *   use for directory separator. If additional array elements exist, these
   *   sequences will also be recognized as directory separators.
   */
  function getDirectorySeparators();

  /**
   * @return string
   *   Gets the preferred character(s) to use for directory separator.
   */
  //function getPreferredDirectorySeparator();

  /**
   * Translates a $path to the format used by another PathParserInterface.
   *
   * E.g., converts directory separators from one file path format to another.
   *
   * @param string $path
   * @param \Curator\FSAccess\PathParser\PathParserInterface $translate_to
   * @return string
   */
  function translate($path, PathParserInterface $translate_to);

  /**
   * Finds the portion of absolute paths that cannot be canonicalized.
   *
   * For example, in the Windows path \\server\share, '\\server' has special
   * meaning and therefore usual path simplification logic should not be applied
   * to it.
   *
   * @param string $path
   * @return string|bool
   *   The first zero or more characters of the beginning of $path that
   *   identify a drive/network server/volume etc.
   *
   *   Relative $paths will always result in a boolean FALSE.
   */
  function getAbsolutePrefix($path);
}
