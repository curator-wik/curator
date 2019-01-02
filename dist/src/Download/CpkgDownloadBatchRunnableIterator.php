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

  /**
   * @var bool $hasRun
   */
  protected $hasRun;

  /** @var int $runnerRank */
  protected $runnerRank;

  public function __construct(StatusService $status_service, RollbackCaptureService $rollback_service, $url, $runner_rank, $last_processed_runnable_id)
  {
    parent::__construct($status_service, $url, $last_processed_runnable_id);
    $this->runnerRank = $runner_rank;
    if ($runner_rank !== 0 || ($runner_rank === 0 && $last_processed_runnable_id > 0)) {
      $this->hasRun = TRUE;
    } else {
      $this->hasRun = FALSE;

      $this->rollbackPrepRunnable = new RollbackCapturePrepBatchRunnable(
        $rollback_service,
        0
      );
    }
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
    if ($this->runnerRank === 0) {
      return ! $this->hasRun;
    } else {
      return parent::valid();
    }
  }

  public function rewind() {
    parent::rewind();
    if ($this->runnerRank === 0) {
      $this->hasRun = FALSE;
    }
  }
}