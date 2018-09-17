<?php


namespace Curator\Download;


use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class CurlDownloadBatchRunnableIterator extends AbstractRunnableIterator {

  /**
   * @var CurlDownloadBatchRunnable $runnable
   */
  protected $runnable;

  /**
   * @var bool $valid
   */
  protected $is_valid;

  public function __construct(StatusService $status_service, $url) {
    $this->runnable = new CurlDownloadBatchRunnable(
      $status_service,
      1,
      $url
    );

    $this->is_valid = TRUE;
  }

  public function current() {
    if ($this->valid()) {
      return $this->runnable;
    }
  }

  public function next() {
    $this->is_valid = FALSE;
  }

  public function valid() {
    return $this->is_valid;
  }

  public function rewind() {
    $this->is_valid = TRUE;
  }
}