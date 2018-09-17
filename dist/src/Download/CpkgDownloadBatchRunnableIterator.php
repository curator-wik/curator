<?php


namespace Curator\Download;
use Curator\FSAccess\FSAccessInterface;
use Curator\Rollback\RollbackCapturePrepBatchRunnable;
use Curator\Rollback\RollbackCaptureService;
use Curator\Status\StatusService;

/**
 * Class CpkgDownloadBatchRunnableIterator
 *
 * This iterator exists to tack on a runnable that prepares the rollback directory
 * while the download is simultaneously in progress.
 */
class CpkgDownloadBatchRunnableIterator extends CurlDownloadBatchRunnableIterator
{
  /**
   * @var RollbackCapturePrepBatchRunnable $rollbackPrepRunnable
   */
  protected $rollbackPrepRunnable;

  protected $hasRun = false;

  public function __construct(StatusService $status_service, RollbackCaptureService $rollback_service, $url)
  {
    parent::__construct($status_service, $url);

    $this->rollbackPrepRunnable = new RollbackCapturePrepBatchRunnable(
      $rollback_service,
      2
    );
  }

  public function current() {
    if (! $this->hasRun) {
      return $this->rollbackPrepRunnable;
    } else {
      return parent::current();
    }
  }

  public function next() {
    if (! $this->hasRun) {
      $this->hasRun = TRUE;
    } else {
      parent::next();
    }
  }

  public function valid() {
    return (! $this->hasRun) || parent::valid();
  }

  public function rewind() {
    $this->hasRun = FALSE;
    parent::rewind();
  }
}