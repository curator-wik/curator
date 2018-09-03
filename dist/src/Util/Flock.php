<?php

namespace Curator\Util;


use Curator\Status\StatusService;

class Flock implements ReaderWriterLockInterface {
  /**
   * @var string $key
   */
  protected $key;

  /**
   * @var resource $fh
   *   File handle to perform advisory locks on.
   */
  protected $filename = '';

  /**
   * @var int $lock_level
   *   The current lock level that is held (LOCK_UN/LOCK_SH/LOCK_EX.)
   */
  protected $lock_level;

  protected static $keys_to_handles = array();

  public function __construct($key) {
    $this->key = $key;
    $this->lock_level = LOCK_UN;
  }

  protected function getFilename() {
    if ($this->filename == '') {
      $this->filename = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'curator_lock_'
        . md5($this->key);
    }
    return $this->filename;
  }

  protected function getFileHandle() {
    if (array_key_exists($this->key, static::$keys_to_handles)) {
      return static::$keys_to_handles[$this->key];
    } else {
      $fh = fopen($this->getFilename(), 'w+');
      static::$keys_to_handles[$this->key] = $fh;
      return $fh;
    }
  }

  protected function acquire($flags, &$would_block) {
    $would_block = FALSE;
    $fh = $this->getFileHandle();
    try {
      return flock($fh, $flags, $would_block);
    } catch (\ErrorException $e) {
      $r = print_r($fh, TRUE);
      throw new \RuntimeException("Failed to acquire lock using $r: " . $e->getMessage(), 0, $e);
    }
  }

  public function acquireShared($nonblocking = FALSE) {
    $would_block = FALSE;
    $flags = LOCK_SH | ($nonblocking ? LOCK_NB : 0);
    $locked = $this->acquire($flags, $would_block);
    if (! $locked && ! $would_block) {
      throw new \RuntimeException('Failed to acquire lock.', 1);
    } else {
      // TODO: Investigate accuracy of this when already held LOCK_EX.
      $this->lock_level = $locked ? LOCK_SH : LOCK_UN;
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
      $this->lock_level = $locked ? LOCK_EX : LOCK_UN;
      return $locked;
    }
  }

  public function release() {
    $temp = FALSE;
    $unlocked = $this->acquire(LOCK_UN, $temp);
    if (! $unlocked) {
      throw new \RuntimeException('Failed to release lock.', 1);
    } else {
      fclose($this->getFileHandle());
      unset(static::$keys_to_handles[$this->key]);
      $this->lock_level = LOCK_UN;
      return $unlocked;
    }
  }

  public function getLockLevel() {
    return $this->lock_level;
  }

}
