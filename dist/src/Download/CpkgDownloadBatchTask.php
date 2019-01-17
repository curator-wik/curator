<?php


namespace Curator\Download;


use Curator\Cpkg\BatchTaskTranslationService;
use Curator\IntegrationConfig;
use Curator\Rollback\RollbackCaptureService;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

/**
 * Class CpkgDownloadBatchTask
 * Creates batch tasks to apply a cpkg upon successful download.
 *
 * Service id: download.cpkg_download_batch_task
 */
class CpkgDownloadBatchTask extends CurlDownloadBatchTask {

  /**
   * @var BatchTaskTranslationService $cpkg_task_builder
   */
  protected $cpkg_task_builder;

  protected $rollback_service;

  public function __construct(StatusService $statusService, BatchTaskTranslationService $cpkg_task_builder, RollbackCaptureService $rollback_service) {
    parent::__construct($statusService);
    $this->cpkg_task_builder = $cpkg_task_builder;
    $this->rollback_service = $rollback_service;
  }

  public function getRunnableIterator(TaskInstanceStateInterface $schedule, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    /**
     * @var CurlDownloadBatchTaskInstanceState $schedule
     */
    return new CpkgDownloadBatchRunnableIterator($this->status_service, $this->rollback_service, $schedule->getUrl(), $runner_rank, $last_processed_runnable_id);
  }

  public function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    parent::onRunnableComplete($schedule, $runnable, $result, $aggregator, $progress);

    // There are two runnables as part of this task, the rollback prep which results in NULL and the actual download,
    // which results in a path to the downloaded file on disk.
    if ($result !== NULL) {
      // Tell the Cpkg\BatchTaskTranslationService to make the batch tasks that
      // will apply the package we've downloaded.
      $this->cpkg_task_builder->makeBatchTasks($result);
    }
  }
}
