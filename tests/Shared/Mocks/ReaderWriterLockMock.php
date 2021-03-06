<?php

namespace Curator\Tests\Shared\Mocks;

use Curator\Util\ReaderWriterLockInterface;

class ReaderWriterLockMock implements ReaderWriterLockInterface {
  protected $lock_level;

  function __construct() {
    $this->lock_level = LOCK_UN;
  }

  function acquireShared($nonblocking = FALSE) {
    if ($this->lock_level != LOCK_EX) {
      $this->lock_level = LOCK_SH;
    }
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
