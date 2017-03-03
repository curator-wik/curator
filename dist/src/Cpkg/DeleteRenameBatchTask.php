<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use mbaynton\BatchFramework\RunnerInterface;

/**
 * Class DeleteRenameBatchTask
 *   Performs the deletions and renames prescribed by one version increment of
 *   a cpkg.
 */
class DeleteRenameBatchTask extends CpkgBatchTask {

  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * DeleteRenameBatchTask constructor.
   */
  public function __construct(CpkgReader $reader, FSAccessManager $fs_access) {
    parent::__construct($reader);
    $this->fs_access = $fs_access;
  }

  public function getRunnerCount($cpkg_path, $version) {
    if ($this->isParallelizable(
      $cpkg_path,
      $version
    )) {
      return 4;
    } else {
      return 1;
    }
  }

  public function isParallelizable($cpkg_path, $version) {
    /*
     * Not safe to run in parallel if:
     * - A directory is renamed to or from X, and other renames are into or out of X/.
     * - X is renamed to Y, then Z is renamed to X.
     */
    $renames = $this->reader->getRenames($cpkg_path, $version);
    $all_impacted_objects = array_merge(array_keys($renames), array_values($renames));
    sort($all_impacted_objects, SORT_STRING);
    $current = next($all_impacted_objects);
    while (($next = next($all_impacted_objects)) !== FALSE) {
      $plus_slash = "$current/";
      if(
        $current === $next
        || (strlen($plus_slash) + 1 >= strlen($next) && strncmp($plus_slash, $next, strlen($plus_slash)) === 0)
      ) {
        return FALSE;
      }
      $current = $next;
    }
    return TRUE;
  }

  public function getRunnableIterator(CpkgBatchTaskInstanceState $instance_state, RunnerInterface $runner, $runner_rank, $num_total_runners, $last_processed_runnable_id) {
    if ($last_processed_runnable_id == 0) {
      $start = $runner_rank;
    } else {
      $start = $last_processed_runnable_id + $num_total_runners;
    }
    return new DeleteRenameBatchRunnableIterator($this->reader, $this->fs_access, $start, $num_total_runners);
  }

}
