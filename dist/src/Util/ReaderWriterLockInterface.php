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
}
