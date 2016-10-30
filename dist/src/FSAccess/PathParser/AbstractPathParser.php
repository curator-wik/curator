<?php

namespace Curator\FSAccess\PathParser;


abstract class AbstractPathParser implements PathParserInterface {

  /**
   * @inheritdoc
   */
  public function translate($path, PathParserInterface $translate_to) {
    if ($this->pathIsAbsolute($path)) {
      $abs_prefix = $this->getAbsolutePrefix($path);
      $translateable_path = substr($path, strlen($abs_prefix));
    } else {
      $abs_prefix = NULL;
      $translateable_path = $path;
    }

    // normalize directory separators
    $translateable_path = $this->normalizeDirectorySeparators($path, $translate_to);

    if ($abs_prefix !== NULL) {
      return $abs_prefix . $translateable_path;
    } else {
      return $translateable_path;
    }
  }

  /**
   * @inheritdoc
   */
  public function normalizeDirectorySeparators($path, PathParserInterface $target_style = NULL) {
    if (empty($target_style)) {
      $target_style = $this;
    }

    return str_replace($this->getDirectorySeparators(),
      $target_style->getDirectorySeparators()[0],
      $path
    );
  }

  /**
   * @inheritDoc
   */
  public function pathIsAbsolute($path) {
    return is_string($this->getAbsolutePrefix($path));
  }

  /**
   * @inheritdoc
   */
  public function baseName($path) {
    $parts = explode(
      $this->getDirectorySeparators()[0],
      str_replace($this->getDirectorySeparators(), $this->getDirectorySeparators()[0], $path)
    );

    for($i = count($parts) - 1; $i >= 0; $i--) {
      if (! empty($parts[$i])) {
        return $parts[$i];
      }
    }

    return '';
  }

  /**
   * @inheritDoc
   */
  public abstract function getAbsolutePrefix($path);

  /**
   * @inheritDoc
   */
  public abstract function getDirectorySeparators();

}
