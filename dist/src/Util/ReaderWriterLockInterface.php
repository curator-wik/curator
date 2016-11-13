<?php

namespace Curator\Util;


interface ReaderWriterLockInterface {
  /**
   * @return bool
   *   TRUE if the lock was acquired, or FALSE if not.
   *
   *   A FALSE outcome is only possible when $nonblocking = TRUE. Failures to
   *   lock due to error conditions result in exceptions being thrown.
   *
   * @throws \RuntimeException
   */
  function acquireShared($nonblocking = FALSE);

  /**
   * @return bool
   *   TRUE if the lock was acquired, or FALSE if not.
   *
   *   A FALSE outcome is only possible when $nonblocking = TRUE. Failures to
   *   lock due to error conditions result in exceptions being thrown.
   *
   * @throws \RuntimeException
   */
  function acquireExclusive($nonblocking = FALSE);

  /**
   * @return void
   * @throws \RuntimeException
   *   If a held lock cannot be released for any reason.
   *
   *   Release()ing a lock the caller does not possess shall not throw an
   *   exception.
   */
  function release();

  /**
   * Gets the current locking level that is held.
   *
   * @return int
   *   One of the following PHP-defined constants:
   *   - LOCK_UN: No lock is held.
   *   - LOCK_SH: A shared lock is held.
   *   - LOCK_EX: An exclusive lock is held.
   */
  function getLockLevel();
}
