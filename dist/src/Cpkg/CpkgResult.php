<?php


namespace Curator\Cpkg;

/**
 * Class CpkgRunnableResult
 *
 * Struct class modeling the type returned by cpkg runnables and tasks.
 */
class CpkgResult {

  /**
   * Number of errors that occurred.
   *
   * @var int $errorCount
   */
  public $errorCount;
}