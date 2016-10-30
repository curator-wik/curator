<?php

namespace Curator\FSAccess;

/**
 * Trait CommonAdapterStreamWrapperTrait
 *   Implements methods common to read and write adapters in terms of php
 *   stream wrappers.
 */
trait CommonAdapterStreamWrapperTrait {
  /**
   * @return StreamContextWrapper
   */
  public abstract function getStreamContext();

  /**
   * @return string
   */
  protected abstract function alterPathForStreamWrapper($path);

  /**
   * Implements ReadAdapterInterface::ls(), WriteAdapterInterface::ls().
   */
  public function ls($directory) {
    try {
      $dh = opendir($this->alterPathForStreamWrapper($directory), $this->getStreamContext()->getContext());

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
      throw new FileException($e->getMessage(), $directory, 0, $e);
    }
  }

  /**
   * Generic/default implementation of a method required by
   * ReadAdapterStreamWrapperTrait and WriteAdapterStreamWrapperTrait. See their
   * docs for details.
   *
   * This implementation just throws a generic FileException.
   *
   * @param string $path
   *   The path that an operation failed on.
   * @param string $read_write
   *   'r' if the operation was a read, 'w' if a write.
   * @param string $operation_description
   *   The message for the exception to contain.
   * @param \ErrorException|NULL $error_exception
   *   Optional ErrorException from the failed operation.
   *
   * @throws FileException
   */
  protected function failPath($path, $read_write, $operation_description, \ErrorException $error_exception = NULL) {
    if ($error_exception) {
      throw new FileException(
        $operation_description . ' Backing filesystem says: ' . $error_exception->getMessage(),
        $path,
        0,
        $error_exception);
    } else {
      throw new FileException($operation_description, $path);
    }
  }
}
