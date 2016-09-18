<?php
/**
 * @file
 * Code blocks tailored for PHP < 5.5.
 *
 * Don't use this file/class directly; it's pulled in as needed from other
 * classes and functions.
 */

namespace Curator\Util;


class pre_5_5 {
  function doCallWithErrorException(callable $call, $error_types, $params) {
    $p = $params;
    $result = NULL;

    try {
      switch (count($params)) {
        case 0:
          $result = $call();
          break;
        case 1:
          $result = $call($p[0]);
          break;
        case 2:
          $result = $call($p[0], $p[1]);
          break;
        case 3:
          $result = $call($p[0], $p[1], $p[2]);
          break;
        case 4:
          $result = $call($p[0], $p[1], $p[2], $p[3]);
          break;
        case 5:
          $result = $call($p[0], $p[1], $p[2], $p[3], $p[4]);
          break;
        default:
          throw new \LogicException('ErrorHandling::withErrorException does not support calls with this many parameters.');
          break;
      }
    } catch (\Exception $e) {
      // return FALSE causes default handler to be run.
      set_error_handler(function(){ return FALSE; }, $error_types);
      throw $e;
    }

    // return FALSE causes default handler to be run.
    set_error_handler(function() { return FALSE; }, $error_types);
    return $result;
  }

  public static function emulateDefaultErrorHandling($severity, $errstr, $file, $line) {

  }
}
