<?php


namespace Curator\Tests\Shared\Traits\Cpkg;

use Curator\Batch\TaskGroup;
use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Tests\Shared\Traits\Batch\WebTestCaseBatchRunnerTrait;
use Symfony\Component\HttpKernel\Client;


trait WebTestCaseCpkgApplierTrait {
  use WebTestCaseBatchRunnerTrait;

  /**
   * @param $cpkg_path
   *   Path to cpkg used for test.
   *
   * @return TaskGroup
   */
  protected function scheduleCpkg($cpkg_path) {
    /**
     * @var BatchTaskTranslationService $translation_svc
     */
    $translation_svc = $this->app['cpkg.batch_task_translator'];
    return $translation_svc->makeBatchTasks($this->p($cpkg_path));
  }

  /**
   * Modifies a path to cpkgs used by this test.
   *
   * Often overridden in the class to create absolute pathnames to fixture
   * packages.
   *
   * @param string $cpkg_path
   * @return string
   */
  protected function p($cpkg_path) {
    return $cpkg_path;
  }

  /**
   * @param string $cpkg_path
   *   The pre p()-translated path to the cpkg.
   * @param Client $client
   *   The client to make the batch runner requests on; cookie jar should be preconfigured.
   * @param int|null $num_tasks
   *   The expected number of tasks that will result from the given cpkg.
   */
  protected function runBatchApplicationOfCpkg($cpkg_path, Client $client, $num_tasks = NULL) {
    // Prep rollback capture area
    $rollback_capture_path = $this->app['status']->getStatus();
    $rollback_capture_path = $rollback_capture_path->rollback_capture_path;
    // $this->app['rollback']->initializeCaptureDir($rollback_capture_path);

    $taskgroup = $this->scheduleCpkg($cpkg_path);
    if ($num_tasks != NULL) {
      $this->assertEquals(
        $num_tasks,
        count(array_unique($taskgroup->taskIds)),
        'Unexpected number of tasks scheduled from ' . $cpkg_path
      );
    }

    /********* End setup, begin execution of client requests as necessary *****/
    $this->app['session']->save();

    $this->runBatchTasks($client, $taskgroup);
  }
}
