<?php


namespace Curator\Persistence;

/**
 * Interface SharedPersistenceInterface
 *  Curator's API for persistent storage.
 *
 *   The usage pattern and operations available in persistent storage are
 *   limited, based on a least-common-denominator installation in which the only
 *   backing storage accessible is a file written through the FS access method.
 *
 * @package Curator\SharedPersistence
 */
interface PersistenceInterface {

  function beginReadOnly();

  function beginReadWrite();

  function end();

  function set($key, $value);

  function get($key, $defaultValue);

}
