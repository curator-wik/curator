<?php


namespace Curator\FSAccess\PathParser;

class WindowsPathParser extends AbstractPathParser {

  public function getAbsolutePrefix($path) {
    $match = [];
    // Prefix explicitly forbidding attempts to parse/canonicalize path.
    if (strncmp($path, '\\\\?\\', 4) === 0) {
      return $path;
    } else if (preg_match('|^\\\\\\\\[^\\\\]+[\\\\/]|', $path, $match)) {
      return $match[0];
    } else if (preg_match("|^[A-Za-z]:[/\\\\]|", $path, $match)) {
      return $match[0];
    }

    /* An additional prefix, "\", is considered absolute *on the current drive*
     * That's still relative to a mutable state of the process, so it is not
     * considered absolute here.
     */
    return FALSE;
  }

  public function getDirectorySeparators() {
    return ['\\', '/'];
  }
}

