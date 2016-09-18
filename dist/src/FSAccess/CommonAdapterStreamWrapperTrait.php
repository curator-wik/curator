<?php


namespace Curator\FSAccess;

/**
 * Trait CommonAdapterStreamWrapperTrait
 *   Implements methods common to read and write adapters in terms of php
 *   stream wrappers.
 */
trait CommonAdapterStreamWrapperTrait {
  /**
   * Implements ReadAdapterInterface::ls(), WriteAdapterInterface::ls().
   */
  public function ls($directory) {
    try {
      $dh = ErrorHandling::withErrorException('opendir',
        E_ALL & ~E_NOTICE,
        array(
          $directory,
          $this->getStreamContext()->getContext()
        )
      );

      $result = [];
      while (FALSE !== ($name = readdir($dh))) {
        if ($name === '.' || $name === '..') {
          continue;
        }
        $result[] = $name;
      }
      sort($result, SORT_STRING);
      return $result;
    } catch (\ErrorException $e) {
      throw new FileException($e->getMessage(), 0, $e);
    }
  }
}
