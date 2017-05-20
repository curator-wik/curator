<?php


namespace Curator\Tests\Shared\Mocks;


use Curator\Persistence\PersistenceException;
use Curator\Persistence\PersistenceInterface;

class BrokenPersistenceMock implements PersistenceInterface {

  function beginReadOnly() {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }

  function beginReadWrite() {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }

  function end() {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }

  function popEnd() {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }

  function set($key, $value) {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }

  function get($key, $defaultValue = NULL) {
    throw new PersistenceException('This PersistenceInterface implementation is intentionally defective.');
  }
}
