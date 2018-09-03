<?php


namespace Curator\Persistence;

use Curator\FSAccess\FileException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\ReadAdapterInterface;
use Curator\IntegrationConfig;
use Curator\FSAccess\FSAccessInterface;
use Curator\Status\StatusService;
use Curator\Util\ReaderWriterLockInterface;

/**
 * Class FilePersistence
 *   Implements application data persistence by writing a file to disk.
 *
 * @package Curator\Persistence
 */
class FilePersistence implements PersistenceInterface
{

  /**
   * A string prepended to the serialized persistence file to ensure the data is
   * ignored by the interpreter in the event the persistence file is accessed
   * directly over HTTP.
   */
  const SAFETY_HEADER = '<?php __HALT_COMPILER();';

  /**
   * @var string $safe_extension
   *   An extension known to be processed by the interpreter on this server.
   */
  protected $safe_extension;

  /**
   * @var string $filename
   *   The path accepted by the FSAccessManager to the persistence store.
   */
  protected $filename = '';

  /**
   * @var FSAccessInterface $fs_access
   *   Injected dependency.
   */
  protected $fs_access;

  /**
   * @var ReadAdapterInterface $direct_reader
   */
  protected $direct_reader;

  /**
   * @var ReaderWriterLockInterface $lock
   *   Injected dependency.
   */
  protected $lock;

  /**
   * @var int $lock_counter
   *   Number of repeated calls to beginReadOnly() and/or beginReadWrite().
   */
  protected $lock_counter;

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
   * @param \Curator\FSAccess\ReadAdapterInterface $direct_reader
   *   The AdapterInterface in use by the FSAccessInterface.
   *   Use of it directly here rather than through the FSAccessManager is exceptional. Typically, we want the
   *   FSAccessManager to wrap all access to the read and write adapters, ensuring the paths accessed are within
   *   the project root. However, since determination of the project root requires an operational persistence service,
   *   we instead build the absolute path to the file backing this FilePersistence in a way that user input cannot
   *   influence, and then read through the read adapter directly here.
   *
   * @param \Curator\Util\ReaderWriterLockInterface $lock
   *   The ReaderWriterLock service.
   *
   * @param string $persistence_directory
   *   The directory our persistence file lives in.
   *
   * @param string $safe_extension
   *   A file extension that is interpreted as PHP by this webserver.
   *   We include this extension in the filename backing the storage to help
   *   ensure the webserver doesn't serve the data out.
   */
  public function __construct(FSAccessInterface $fs_access, ReadAdapterInterface $direct_reader, ReaderWriterLockInterface $lock, $persistence_directory, $safe_extension)
  {
    $this->fs_access = $fs_access;
    $this->direct_reader = $direct_reader;
    $this->lock = $lock;
    $this->safe_extension = $safe_extension;
    $this->lock_counter = 0;
    $this->filename = $persistence_directory . $direct_reader->getPathParser()->getDirectorySeparators()[0] . '.curator-data.' . $this->safe_extension;
  }

  public function _write()
  {
    $raw = self::SAFETY_HEADER . serialize($this->_values);
    try {
      $this->fs_access->filePutContents($this->getFilename(), $raw);
    } catch (FileException $e) {
      throw new PersistenceException("Failed writing to persistent data file. Inner exception may provide specifics.", 0, $e);
    }
  }

  /**
   * @inheritdoc
   */
  public function set($key, $value)
  {
    if ($this->lock->getLockLevel() != LOCK_EX) {
      throw new \LogicException('Attempted to persist data without a write lock.');
    }

    $this->ensureValuesDictionary();
    $exists = array_key_exists($key, $this->_values);
    if ($value === NULL && $exists) {
      $this->unpersisted_changes = TRUE;
      unset($this->_values[$key]);
    } else if (($exists && $this->_values[$key] !== $value) || !$exists) {
      $this->unpersisted_changes = TRUE;
      $this->_values[$key] = $value;
    }
  }

  /**
   * @inheritdoc
   */
  public function get($key, $defaultValue = NULL)
  {
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

  public function beginReadOnly()
  {
    $this->lock_counter++;
    if ($this->lock->getLockLevel() == LOCK_UN) {
      try {
        $this->lock->acquireShared();
      } catch (\Exception $e) {
        throw new PersistenceException('An error occurred while attempting to obtain a read lock. Inner exception may have specifics.', 0, $e);
      }
    }
  }

  public function beginReadWrite()
  {
    $this->lock_counter++;
    if ($this->lock->getLockLevel() != LOCK_EX) {
      try {
        $this->lock->acquireExclusive();
      } catch (\Exception $e) {
        throw new PersistenceException('An error occurred while attempting to obtain a write lock. Inner exception may have specifics.', 0, $e);
      }
    }
  }

  public function popEnd()
  {
    if ($this->lock_counter == 0) {
      return;
    } else if ($this->lock_counter == 1) {
      $this->end();
    } else {
      $this->lock_counter--;
    }
  }

  protected function getFilename()
  {
    return $this->filename;
  }

  protected function ensureValuesDictionary()
  {
    if ($this->_values === NULL) {
      $this->_values = $this->_read();
      if (! is_array($this->_values)) {
        $this->_values = [];
      }
    }

    return $this->_values;
  }

  protected function _read()
  {
    try {
      $raw = $this->direct_reader->fileGetContents($this->getFilename());
      // need to skip initial bytes of safety header
      return unserialize(substr($raw, strlen(self::SAFETY_HEADER)));
    } catch (FileNotFoundException $e) {
      return [];
    } catch (FileException $e) {
      throw new PersistenceException("Failed reading persistent data file. Inner exception may provide specifics.", 0, $e);
    }
  }

  /**
   * @inheritdoc
   */
  public function end()
  {
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
    $this->lock_counter = 0;
  }
}
