<?php


namespace Curator\Tests\Shared\Mocks;


use Curator\Persistence\PersistenceInterface;

class InMemoryPersistenceMock implements PersistenceInterface {
  /**
   * @var ReaderWriterLockMock $lock
   */
  protected $lock;

  protected $data;

  /**
   * @var int $lock_counter
   */
  protected $lock_counter;

  public function __construct() {
    $this->lock = new ReaderWriterLockMock();
    $this->lock_counter = 0;
    $this->clear();
  }

  public function beginReadOnly() {
    $this->lock->acquireShared();
    $this->lock_counter++;
  }

  public function beginReadWrite() {
    $this->lock->acquireExclusive();
    $this->lock_counter++;
  }

  public function end() {
    $this->lock->release();
    $this->lock_counter = 0;
  }

  public function popEnd() {
    if ($this->lock_counter == 0) {
      return;
    } else if ($this->lock_counter == 1) {
      $this->end();
    } else {
      $this->lock_counter--;
    }
  }

  public function set($key, $value) {
    if ($this->lock->getLockLevel() != LOCK_EX) {
      throw new \LogicException('Attempt to set persistent data without write lock.');
    }
    $this->data[$key] = $value;
  }

  public function get($key, $defaultValue = NULL) {
    if ($this->lock->getLockLevel() == LOCK_UN) {
      throw new \LogicException('Attempt to read persistent data without read lock.');
    }
    if (array_key_exists($key, $this->data)) {
      return $this->data[$key];
    } else {
      return $defaultValue;
    }
  }

  /**
   * For test usage only; not part of PersistenceInterface.
   */
  public function clear() {
    $this->data = [];
  }

  /**
   * Resets the contents of this InMemoryPersistenceMock to the given array.
   *
   * @param array $data
   *   Associative array; keys match those passed to get().
   */
  public function loadMockedData($data) {
    $this->data = $data;
  }
}
