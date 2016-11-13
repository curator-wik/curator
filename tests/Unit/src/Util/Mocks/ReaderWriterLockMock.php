<?php

namespace Curator\Tests\Unit\Util\Mocks;

use Curator\Util\ReaderWriterLockInterface;

class ReaderWriterLockMock implements ReaderWriterLockInterface {
  protected $lock_level;

  function __construct() {
    $this->lock_level = LOCK_UN;
  }

  function acquireShared($nonblocking = FALSE) {
    $this->lock_level = LOCK_SH;
    return TRUE;
  }

  function acquireExclusive($nonblocking = FALSE) {
    $this->lock_level = LOCK_EX;
    return TRUE;
  }

  function release() {
    $this->lock_level = LOCK_UN;
  }

  function getLockLevel() {
    return $this->lock_level;
  }
}
