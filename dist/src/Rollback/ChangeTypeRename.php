<?php


namespace Curator\Rollback;


class ChangeTypeRename extends Change
{
  /**
   * ChangeTypeWrite constructor.
   *
   * @param string $source
   *   The path where the file is about to be renamed from.
   * @param string $target
   *   The path where the file is about to be renamed to.
   */

  /**
   * @var string $source
   */
  protected $source;

  public function __construct($source, $target)
  {
    parent::__construct(Change::OPERATION_RENAME, $target);
    $this->source = $source;
  }

  public function getSource() {
    return $this->source;
  }
}
