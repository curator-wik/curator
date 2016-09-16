<?php


namespace Curator\FSAccess;

/**
 * Trait PathSimplificationTrait
 *   Provides a method for removing extraneous elements from a path.
 */
trait PathSimplificationTrait {

  /**
   * Gets the path parser whose rules to use when simplifying paths.
   * 
   * @return PathParser\PathParserInterface
   */
  abstract function getPathParser();
  
  public function simplifyPath($path) {
    $is_absolute = $this->getPathParser()->pathIsAbsolute($path);

    if ($is_absolute) {
      $abs_prefix = $this->getPathParser()->getAbsolutePrefix($path);
      $simplify_path = substr($path, strlen($abs_prefix));
    } else {
      $abs_prefix = NULL;
      $simplify_path = $path;
    }

    // Normalize directory separators
    $separators = $this->getPathParser()->getDirectorySeparators();
    $normalized_separator = reset($separators);
    $simplify_path = str_replace($separators,
      $normalized_separator,
      $simplify_path);

    // Unroll parent directory references, ../
    $path_elements = [];
    $depth = 0;
    $mindepth = 0;
    foreach (explode($normalized_separator, $simplify_path) AS $element) {
      // Clean out occurrences of '//' or '/./'
      if ($element === '' || $element === '.') {
        continue;
      }
      if ($element == '..') {
        if (count($path_elements) && end($path_elements) != '..') {
          array_pop($path_elements);
        } else {
          $path_elements[] = '..';
        }
        $depth--;
        $mindepth = min($mindepth, $depth);
      } else {
        $depth++;
        $path_elements[] = $element;
      }
    }

    if ($mindepth == 0) {
      if (count($path_elements)) {
        return
          ($is_absolute ? $abs_prefix : '') .
          implode($normalized_separator, $path_elements);
      } else {
        return $is_absolute ? $normalized_separator : '.';
      }
    } else {
      return
        '.' . $normalized_separator .
        implode($normalized_separator, $path_elements);
    }

  }
}
