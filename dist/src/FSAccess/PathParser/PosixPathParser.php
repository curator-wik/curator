<?php
/**
 * Created by PhpStorm.
 * User: mbaynton
 * Date: 8/12/16
 * Time: 8:23 AM
 */

namespace Curator\FSAccess\PathParser;

use Curator\FSAccess\AbstractPathParser;

/**
 * Class PosixPathParser
 *   Path interpretation and resolution peculiarities to the POSIX standard.
 *   See http://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap04.html#tag_04_12
 *
 *   This path parser is generally appropriate for mounted filesystems on *nix.
 */
class PosixPathParser extends AbstractPathParser {

  /**
   * Determines if the $path is absolute according to POSIX.
   *
   * @param string $path
   * @return bool
   *   {@inheritdoc}
   */
  public function pathIsAbsolute($path) {
    return strncmp('/', $path, 1) === 0;
  }

  /**
   * @inheritdoc
   */
  public function getAbsolutePrefix($path) {
    if ($this->pathIsAbsolute($path)) {
      return '/';
    } else {
      return '';
    }
  }

  public function getDirectorySeparators() {
    return ['/'];
  }
}