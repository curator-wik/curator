<?php


namespace Curator\Rollback;


class ChangeTypeDelete extends Change
{
  /**
   * ChangeTypeWrite constructor.
   * @param string $target
   *   The path where a file is about to be deleted.
   */
  public function __construct($target)
  {
    parent::__construct(Change::OPERATION_DELETE, $target);
  }
}
