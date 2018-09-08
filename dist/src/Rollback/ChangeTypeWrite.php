<?php


namespace Curator\Rollback;


class ChangeTypeWrite extends Change
{
  /**
   * ChangeTypeWrite constructor.
   * @param string $target
   *   The path where a file is about to be written.
   */
  public function __construct($target)
  {
    parent::__construct(Change::OPERATION_WRITE, $target);
  }
}