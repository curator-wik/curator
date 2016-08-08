<?php


namespace Curator\FSAccess;

/**
 * Trait PathSimplificationTrait
 *   Provides a method for removing extraneous elements from a path.
 */
trait PathSimplificationTrait {
  protected function simplifyPath($path) {
    // Normalize \ separators to /
    $path = str_replace('\\', '/', $path);

    $is_absolute = strncmp('/', $path, 1) === 0;

    // Unroll parent directory references, ../
    $path_elements = [];
    $depth = 0;
    $mindepth = 0;
    foreach (explode('/', $path) AS $element) {
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
          ($is_absolute ? '/' : '') .
          implode('/', $path_elements);
      } else {
        return $is_absolute ? '/' : '.';
      }
    } else {
      return
        './' .
        implode('/', $path_elements);
    }

  }
}
