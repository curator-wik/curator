<?php


namespace Curator\Cpkg;

/**
 * Class CpkgClassificationService
 *
 * Provides methods that examine and classify a cpkg.
 *
 * Currently it just decides whether the delete/rename operations can be performed
 * concurrently and unsynchronized. This code used to just live in
 * DeleteRenameBatchTask but was removed to fix a dependency cycle.
 */
class CpkgClassificationService
{
  /**
   * @var CpkgReader $reader
   */
  protected $reader;

  public function __construct(CpkgReader $reader)
  {
    $this->reader = $reader;
  }

  /**
   * Determines whether it is safe to apply deletes and renames in a concurrent
   * and unsynchronized manner.
   *
   * @param string $cpkg_path
   * @param string $version
   * @return bool
   */
  public function isParallelizableDeleteRename($cpkg_path, $version) {
    /*
     * Not safe to run in parallel if:
     * - A directory is renamed to or from X, and other renames are into or out of X/.
     * - X is renamed to Y, then Z is renamed to X.
     */
    $renames = $this->reader->getRenames($cpkg_path, $version);
    $all_impacted_objects = array_merge(array_keys($renames), array_values($renames));
    sort($all_impacted_objects, SORT_STRING);
    $current = reset($all_impacted_objects);
    while (($next = next($all_impacted_objects)) !== FALSE) {
      $plus_slash = "$current/";
      if(
        $current === $next
        || (strlen($next) >= strlen($plus_slash) + 1 && strncmp($plus_slash, $next, strlen($plus_slash)) === 0)
      ) {
        return FALSE;
      }
      $current = $next;
    }
    return TRUE;
  }

  /**
   * Determines how many batch task runners to launch when applying the delete
   * and rename tasks prescribed by a cpkg.
   *
   * @param string $cpkg_path
   * @param string $version
   * @return int
   */
  public function getRunnerCountDeleteRename($cpkg_path, $version) {
    if ($this->isParallelizableDeleteRename(
      $cpkg_path,
      $version
    )) {
      return 4;
    } else {
      return 1;
    }
  }
}