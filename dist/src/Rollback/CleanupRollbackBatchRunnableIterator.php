<?php


namespace Curator\Rollback;


use Curator\Batch\SingleRunnableIterator;
use Curator\FSAccess\FSAccessInterface;
use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class CleanupRollbackBatchRunnableIterator extends SingleRunnableIterator {
  public function __construct(FSAccessInterface $fs_access) {
    $this->runnable = new CleanupRollbackBatchRunnable(
      1,
      $fs_access
    );

    parent::__construct();
  }
}