<?php


namespace Curator\Rollback;


use Curator\Batch\SingleRunnableIterator;

class DoRollbackBatchRunnableIterator extends SingleRunnableIterator {

  public function __construct(RollbackCaptureService $rollback_service) {
    $this->runnable = new DoRollbackBatchRunnable(
      1,
      $rollback_service
    );

    parent::__construct();
  }
}