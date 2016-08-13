<?php

namespace Curator\FSAccess;


abstract class AbstractPathParser implements PathParserInterface {

  /**
   * @inheritdoc
   */
  function translate($path, PathParserInterface $translate_to) {
    if ($this->pathIsAbsolute($path)) {
      $abs_prefix = $this->getAbsolutePrefix($path);
      $translateable_path = substr($path, strlen($abs_prefix));
    } else {
      $abs_prefix = NULL;
      $translateable_path = $path;
    }

    // normalize directory separators
    $translateable_path = str_replace($this->getDirectorySeparators(),
      reset($translate_to->getDirectorySeparators()),
      $translateable_path);

    if ($abs_prefix !== NULL) {
      return $abs_prefix . $translateable_path;
    } else {
      return $translateable_path;
    }
  }

  /**
   * @inheritDoc
   */
  abstract function pathIsAbsolute($path);

  /**
   * @inheritDoc
   */
  abstract function getAbsolutePrefix($path);

  /**
   * @inheritDoc
   */
  abstract function getDirectorySeparators();

}