<?php

namespace Curator\FSAccess\PathParser;

/**
 * Class GenericPathParser
 *   Lacks extreme precision in path semantics, but makes assumptions that
 *   mostly do the "right thing." This is convenient if you do not know which
 *   filesystem's path resolution rules to use.
 */
class GenericPathParser extends AbstractPathParser {

  /**
   * @var \Curator\FSAccess\PathParser\WindowsPathParser $windows
   */
  protected $windows;
  /**
   * @var \Curator\FSAccess\PathParser\PosixPathParser $posix
   */
  protected $posix;

  public function __construct() {
    $this->windows = new WindowsPathParser();
    $this->posix  = new PosixPathParser();
  }

  public function getAbsolutePrefix($path) {
    $p = $this->posix->getAbsolutePrefix($path);
    if ($p !== FALSE) {
      return $p;
    } else {
      return $this->windows->getAbsolutePrefix($path);
    }
  }

  public function getDirectorySeparators() {
    return ['/', '\\'];
  }
}