<?php


namespace Curator\Rollback;

/**
 * Class RollbackCaptureNoOpService
 *   A no-operations implementation of RollbackCaptureInterface.
 *
 *   This class is injected into the cpkg application runnables instead of
 *   RollbackCaptureService when the cpkg being applied is actually performing
 *   a rollback.
 *
 *  Service Id: rollback.no-op
 */
class RollbackCaptureNoOpService implements RollbackCaptureInterface
{
  public function initializeCaptureDir($captureDir){}

  public function capture(Change $change, $captureDir, $runnerId = ''){}

  public function fixupToCpkg($captureDir){}
}