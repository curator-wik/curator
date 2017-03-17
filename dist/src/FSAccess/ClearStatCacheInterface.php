<?php


namespace Curator\FSAccess;

/**
 * Interface ClearStatCacheInterface
 *   An interface for read adapters that cache file information to implement.
 *
 *   The FSAccessManager will make cache clear calls to the read adapter after
 *   moving or removing files, if the read adapter implements this interface.
 */
interface ClearStatCacheInterface {
  /**
   * Clears a file stat cache for a specified path, or all paths.
   *
   * @param string|null $path
   *   The path whose stat cache should be cleared, or NULL to clear everything.
   * @return void
   */
  function clearstatcache($path = NULL);
}
