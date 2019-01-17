<?php


namespace Curator\Rollback;


class ChangeTypeMkdirTree extends Change
{
  /**
   * ChangeTypeWrite constructor.
   * @param string[] $targets
   *   The paths where new directories are about to be created.
   */
  public function __construct($targets)
  {
    parent::__construct(Change::OPERATION_MKDIRTREE, $targets);
  }
}