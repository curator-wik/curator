<?php


namespace Curator\Rollback;

/**
 * Class Change
 *
 * The Change / ChangeType* classes communicate intent to make a particluar
 * type of change to the RollbackCaptureService.
 */
abstract class Change
{
  const OPERATION_WRITE  = 1;
  const OPERATION_PATCH  = 2;
  const OPERATION_DELETE = 3;
  const OPERATION_RENAME = 4;

  /**
   * @var int $operation
   *   A Change::OPERATION_* constant
   */
  protected $operation;

  /**
   * @var string $target
   *   The path where the operation is about to occur.
   */
  protected $target;

  protected function __construct($operation, $target)
  {
    $this->operation = $operation;
    $this->target = $target;
  }

  /**
   * @return int
   *   A Change::OPERATION_* constant
   */
  public function getType() {
    return $this->operation;
  }

  /**
   * @return string
   *   The path where the operation is about to occur.
   */
  public function getTarget() {
    return $this->target;
  }
}
