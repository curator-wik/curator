<?php


namespace Curator\Download;


use Curator\Cpkg\BatchTaskTranslationService;
use Curator\IntegrationConfig;
use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

/**
 * Class CpkgDownloadBatchTask
 * Creates batch tasks to apply a cpkg upon successful download.
 */
class CpkgDownloadBatchTask extends CurlDownloadBatchTask {

  /**
   * @var BatchTaskTranslationService $cpkg_task_builder
   */
  protected $cpkg_task_builder;

  public function __construct(IntegrationConfig $integration_config, BatchTaskTranslationService $cpkg_task_builder) {
    parent::__construct($integration_config);
    $this->cpkg_task_builder = $cpkg_task_builder;
  }

  public function onRunnableComplete(TaskInstanceStateInterface $schedule, RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) {
    parent::onRunnableComplete($schedule, $runnable, $result, $aggregator, $progress);

    // Tell the Cpkg\BatchTaskTranslationService to make the batch tasks that
    // will apply the package we've downloaded.
    $this->cpkg_task_builder->makeBatchTasks($result);
  }
}
