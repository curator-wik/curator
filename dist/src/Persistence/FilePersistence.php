<?php


namespace Curator\Persistence;
use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;
use Curator\IntegrationConfig;
use Curator\FSAccess\FSAccessInterface;
use Curator\Util\ReaderWriterLockInterface;

/**
 * Class FilePersistence
 *   Implements application data persistence by writing a file to disk.
 *
 * @package Curator\Persistence
 */
class FilePersistence implements PersistenceInterface {

  /**
   * A string prepended to the serialized persistence file to ensure the data is
   * ignored by the interpreter in the event the persistence file is accessed
   * directly over HTTP.
   */
  const SAFETY_HEADER = '<?php __HALT_COMPILER();';

  /**
   * @var string $filename
   *   The path accepted by the FSAccessManager to the persistence store.
   */
  protected $filename;

  /**
   * @var FSAccessInterface $fs_access
   *   Injected dependency.
   */
  protected $fs_access;

  /**
   * @var ReaderWriterLockInterface $lock
   *   Injected dependency.
   */
  protected $lock;

  /**
   * @var IntegrationConfig $integration_config
   *   Injected dependency.
   */
  protected $integration_config;

  /**
   * @var mixed[]
   *   Associative array cache of all persisted values while lock is held.
   */
  protected $_values;

  /**
   * @var bool $unpersisted_changes
   *   Tracked to determine whether a write is necessary on end().
   */
  protected $unpersisted_changes;

  /**
   * FilePersistence constructor.
   *
   * @param \Curator\FSAccess\FSAccessInterface $fs_access
   *   The filesystem access service.
   *
   * @param \Curator\Util\ReaderWriterLockInterface $lock
   *   The ReaderWriterLock service.
   *
   * @param IntegrationConfig $integration_config
   * @param string $safe_extension
   *   A file extension that is interpreted as PHP by this webserver.
   *
   *   We include this extension in the filename backing the storage to help
   *   ensure the webserver doesn't serve the data out.
   */
  public function __construct(FSAccessInterface $fs_access, ReaderWriterLockInterface $lock, IntegrationConfig $integration_config, $safe_extension) {
    $this->fs_access = $fs_access;
    $this->lock = $lock;
    $this->integration_config = $integration_config;

    $this->filename = $this->fs_access->ensureTerminatingSeparator($this->integration_config->getSiteRootPath())
      . '.curator-data' . $safe_extension;
  }

  protected function ensureValuesDictionary() {
    if ($this->_values === NULL) {
      $this->_values = $this->_read();
    }

    return $this->_values;
  }

  protected function _read() {
    try {
      $raw = $this->fs_access->fileGetContents($this->filename);
      // First 24 bytes are __HALT_COMPILER(); safety header
      return unserialize(substr($raw, strlen(self::SAFETY_HEADER)));
    } catch (FileNotFoundException $e) {
      return [];
    } catch (FileException $e) {
      throw new PersistenceException("Failed reading persistent data file. Inner exception may provide specifics.", 0, $e);
    }
  }

  public function _write() {
    $raw = self::SAFETY_HEADER . serialize($this->_values);
    try {
      $this->fs_access->filePutContents($this->filename, $raw);
    } catch (FileException $e) {
      throw new PersistenceException("Failed writing to persistent data file. Inner exception may provide specifics.", 0, $e);
    }
  }

  /**
   * @inheritdoc
   */
  public function set($key, $value) {
    if ($this->lock->getLockLevel() != LOCK_EX) {
      throw new \LogicException('Attempted to persist data without a write lock.');
    }

    $this->ensureValuesDictionary();
    $exists = array_key_exists($key, $this->_values);
    if ($value === NULL && $exists) {
      $this->unpersisted_changes = TRUE;
      unset($this->_values[$key]);
    } else if (($exists && $this->_values[$key] !== $value) || ! $exists) {
      $this->unpersisted_changes = TRUE;
      $this->_values[$key] = $value;
    }
  }

  /**
   * @inheritdoc
   */
  public function get($key, $defaultValue = NULL) {
    if ($this->lock->getLockLevel() == LOCK_UN) {
      throw new \LogicException('Attempted to access persistent data without a lock.');
    }

    $this->ensureValuesDictionary();
    $exists = array_key_exists($key, $this->_values);
    if ($exists) {
      return $this->_values[$key];
    } else {
      return $defaultValue;
    }
  }

  public function beginReadOnly() {
    try {
      $this->lock->acquireShared();
    } catch (\Exception $e) {
      throw new PersistenceException('An error occurred while attempting to obtain a read lock. Inner exception may have specifics.', 0, $e);
    }
  }

  public function beginReadWrite() {
    try {
      $this->lock->acquireExclusive();
    } catch (\Exception $e) {
      throw new PersistenceException('An error occurred while attempting to obtain a write lock. Inner exception may have specifics.', 0, $e);
    }
  }

  /**
   * @inheritdoc
   */
  public function end() {
    $lock_level = $this->lock->getLockLevel();
    if ($lock_level == LOCK_EX) {
      if ($this->unpersisted_changes) {
        $this->_write();
        $this->unpersisted_changes = FALSE;
      }
    } else {
      if ($this->unpersisted_changes) {
        // Shoudln't be possible.
        throw new PersistenceException('A writer lock does not appear to be held, but changes were accepted. Some changes to be persited may have been lost.', 127);
      }
    }

    $this->_values = NULL;
    $this->lock->release();
  }
}
