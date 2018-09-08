<?php


namespace Curator\Rollback;


class ChangeTypePatch extends Change
{
  /**
   * ChangeTypePatch constructor.
   * @param string $target
   *   The path where a file is about to be written.
   */
  public function __construct($target)
  {
    parent::__construct(Change::OPERATION_PATCH, $target);
  }
}