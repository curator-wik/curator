<?php

namespace Curator\Rollback;


/**
 * Class RollbackCaptureInterface
 */
interface RollbackCaptureInterface
{
  /**
   * Prepares a directory to be the recipient of rollback capture data.
   *
   * This typically involves emptying the directory and laying out the
   * metadata files to make it appear similar to a cpkg.
   *
   * @param string $captureDir
   *   The directory to prepare.
   * @return void
   */
  public function initializeCaptureDir($captureDir);

  /**
   * Informs the rollback capture service of your intent to make a change.
   *
   * The necessary information to reverse the change is recorded as a result of this call.
   *
   * @param Change $change
   *   A Change object describing the change you intend to make.
   * @param string $captureDir
   *   The directory under which the CaptureService may record its changes.
   *   Original code files and metadata are copied here; it should be secure from the public.
   * @param string|int $runnerId
   *   Optional. When used with the batch framework, this causes a separate copy of some metadata
   *   files to be created per concurrent runner so as to avoid corruption or data loss from concurrent
   *   read/copy/write operations.
   */
  public function capture(Change $change, $captureDir, $runnerId = '');

  /**
   * Transforms the almost-cpkg rollback capture directory into a correct cpkg structure.
   *
   * @param string $captureDir
   */
  public function fixupToCpkg($captureDir);
}