<?php

namespace Curator\Util;


class Flock implements ReaderWriterLockInterface {

  /**
   * @var resource $fh
   *   File handle to perform advisory locks on.
   */
  protected $fh;

  /**
   * @var string $key
   *   The string uniquely identifying this lock.
   */
  protected $key;

  protected static $keys_to_handles = array();

  public function __construct($key) {
    $this->key = $key;
    if (array_key_exists($key, static::$keys_to_handles)) {
      $this->fh = static::$keys_to_handles[$key];
    } else {
      $filename = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'curator_lock_'
        . md5($key);

      $this->fh = fopen($filename, 'w+');
      static::$keys_to_handles[$key] = $this->fh;
    }
  }

  protected function acquire($flags, &$would_block) {
    $would_block = FALSE;
    try {
      return flock($this->fh, $flags, $would_block);
    } catch (\ErrorException $e) {
      throw new \RuntimeException('Failed to acquire lock: ' . $e->getMessage(), 0, $e);
    }
  }

  public function acquireShared($nonblocking = FALSE) {
    $would_block = FALSE;
    $flags = LOCK_SH | ($nonblocking ? LOCK_NB : 0);
    $locked = $this->acquire($flags, $would_block);
    if (! $locked && ! $would_block) {
      throw new \RuntimeException('Failed to acquire lock.', 1);
    } else {
      return $locked;
    }
  }

  public function acquireExclusive($nonblocking = FALSE) {
    $flags = LOCK_EX | ($nonblocking ? LOCK_NB : 0);
    $would_block = FALSE;
    $locked = $this->acquire($flags, $would_block);
    if (! $locked && ! $would_block) {
      throw new \RuntimeException('Failed to acquire lock.', 1);
    } else {
      return $locked;
    }
  }

  public function release() {
    $temp = FALSE;
    $unlocked = $this->acquire(LOCK_UN, $temp);
    if (! $unlocked) {
      throw new \RuntimeException('Failed to release lock.', 1);
    } else {
      fclose($this->fh);
      unset(static::$keys_to_handles[$this->key]);
      return $unlocked;
    }
  }

}
