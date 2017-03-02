<?php


namespace Curator\Cpkg;


use Curator\Task\TaskInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\RunnerInterface;

/**
 * Class DeleteRenameBatchTask
 *   Performs the deletions and renames prescribed by one version increment of
 *   a cpkg.
 */
class DeleteRenameBatchTask extends CpkgBatchTask {

  /**
   * @var int $runnable_count_cache
   */
  protected $runnable_count_cache;

  /**
   * @var int $max_runners
   *   Retains whether the deletes/renames in this cpkg are safe to run in
   *   parallel.
   */
  protected $max_runners;


  /**
   * DeleteRenameBatchTask constructor.
   * @param string $cpkg_path
   * @param string $version
   */
  public function __construct($cpkg_path, $version) {
    parent::__construct($cpkg_path, $version);
    $this->runnable_count_cache = NULL;

    if ($this->isParallelizable()) {
      $this->max_runners = 4;
    } else {
      $this->max_runners = 1;
    }
  }

  protected function _getSerializedProperties() {
    return array_merge(
      parent::_getSerializedProperties(),
      ['runnable_count_cache', 'max_runners']
    );
  }

  public function getNumRunnables() {
    if ($this->runnable_count_cache === NULL) {
      // Count lines in the delete and rename files.
      $count = 0;
      $reader = new ArchiveFileReader($this->cpkg_path);
      foreach (['renamed_files', 'deleted_files'] as $filename) {
        $list = trim($reader->tryGetContent(sprintf('payload/%s/%s',
          $this->version,
          $filename
          )
        ));
        if (! empty($list)) {
          // First line doesn't require newline
          $count++;
          $count += substr_count($list, $this->entry_delimiter);
        }
        unset($list);
      }
      $this->runnable_count_cache = $count;
    }

    return $this->runnable_count_cache;
  }

  public function getMinRunners() {
    return 1;
  }

  public function getMaxRunners() {
    return 4;
  }

  public function isParallelizable($cpkg_path, $version) {
    /*
     * Not safe to run in parallel if:
     * - A directory is renamed to X, and other renames are into or out of X/.
     * - X is renamed to Y, then Z is renamed to X.
     */

  }

  public function getRunnableIterator(RunnerInterface $runner, $runner_rank, $num_total_runners, $last_processed_runnable_id) {
    // TODO: Implement getRunnableIterator() method.
  }

}
