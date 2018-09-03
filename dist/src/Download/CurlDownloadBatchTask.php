<?php


namespace Curator\Download;


use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CurlDownloadBatchTask
 * This class downloads things using cURL using the Batch framework.
 * It currently only supports sequential, single downloads with one runnable.
 *
 * It is likely useful to extend this class with more interesting
 * implementations of onRunnableComplete() that do things with the download.
 *
 * Service id: download.curl_download_batch_task
 */
class CurlDownloadBatchTask implements TaskInterface {

  /**
   * @var StatusService $status_service
   */
  protected $status_service;

  public function __construct(StatusService $statusService) {
    $this->status_service = $statusService;
  }

  public function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    // Since one Runnable downloads entire files, you can act on the download
    // by overriding this method.
  }

  public function getRunnableIterator(TaskInstanceStateInterface $schedule, RunnerInterface $runner, $runner_rank, $last_processed_runnable_id) {
    /**
     * @var CurlDownloadBatchTaskInstanceState $schedule
     */
    return new CurlDownloadBatchRunnableIterator($this->status_service, $schedule->getUrl());
  }

  public function onRunnableError(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $exception, ProgressInfo $progress) { }

  public function assembleResultResponse($final_results) {
    return new Response();
  }

  public function supportsReduction() {
    return FALSE;
  }

  public function supportsUnaryPartialResult() {
    return FALSE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) { }

  public function updatePartialResult($new, $current = NULL) { }

}
