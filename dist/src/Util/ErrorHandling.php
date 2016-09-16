<?php

namespace Curator\Util;

class ErrorHandling {
  protected static $withExceptionCaller = NULL;

  public static function withErrorException(callable $call, $error_types, $params = array()) {
    set_error_handler(function($severity, $errstr, $file, $line){
      static::errorExceptionHandler($severity, $errstr, $file, $line);
    }, $error_types);

    if (self::$withExceptionCaller === NULL) {
      if (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 5) {
        self::$withExceptionCaller = new pre_5_5();
      } else {
        self::$withExceptionCaller = new post_5_5();
      }
    }

    return self::$withExceptionCaller->doCallWithErrorException($call, $error_types, $params);
  }

  protected static function errorExceptionHandler($severity, $errstr, $file, $line) {
    throw new \ErrorException($errstr, 0, $severity, $file, $line);
  }
}
