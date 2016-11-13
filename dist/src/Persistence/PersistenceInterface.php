<?php


namespace Curator\Persistence;

/**
 * Interface SharedPersistenceInterface
 *   Curator's API for persistent storage.
 *
 *   The usage pattern and operations available in persistent storage are
 *   limited, based on a least-common-denominator installation in which the only
 *   backing storage accessible is a file written through the FS access method.
 *   In this limited model, updating a single value requires rewriting all
 *   values in the on-disk file. To coordinate e.g. several apache workers that
 *   simultaneously wish to write modified values, a reader/writer lock is
 *   mandatory to acquire before getting or setting a persisted value.
 *
 *   You should assume a high execution cost to obtaining and releasing locks,
 *   but a low cost of getting and setting values while holding a lock.
 *
 * @package Curator\SharedPersistence
 */
interface PersistenceInterface {

  /**
   * Acquires a reader lock and makes the get() method available to call.
   *
   * @return void
   * @throws PersistenceException
   */
  function beginReadOnly();

  /**
   * Acquires a read/write lock and makes the get() and set() methods available
   * to call.
   *
   * @return void
   * @throws PersistenceException
   */
  function beginReadWrite();

  /**
   * Releases any existing lock on the persistence store.
   *
   * @return void
   * @throws PersistenceException
   */
  function end();

  /**
   * Sets (or overwrites) a key and its corresponding value.
   *
   * Calls to this method must occur after calling beginReadWrite() and before
   * calling end().
   *
   * @param string $key
   *   The key to store the value under.
   * @param string|NULL $value
   *   The value to store.
   *   NULL causes the key to be unset.
   * @return void
   * @throws PersistenceException
   *   In the event of a system or configuration error.
   * @throws \LogicException
   *   If set() was called before beginReadWrite().
   */
  function set($key, $value);

  /**
   * Gets the value associated to a previously set key.
   *
   * Calls to this method must occur after calling either beginReadOnly() or
   * beginReadWrite(), but before calling end().
   *
   * @param string $key
   *   The key whose value is to be retrieved.
   * @param string|NULL $defaultValue
   *   A value to return in the event that the key has not been previously set.
   *   Defaults to NULL.
   * @return string|NULL
   *   The string associated with the param $key if one has been set, or
   *   the param $defaultValue otherwise.
   * @throws PersistenceException
   * @throws \LogicException
   *   When get() was called before beginReadOnly() or beginReadWrite().
   */
  function get($key, $defaultValue = NULL);

}
