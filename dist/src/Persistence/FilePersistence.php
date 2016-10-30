<?php


namespace Curator\Persistence;
use Curator\IntegrationConfig;

/**
 * Class FilePersistence
 *   Implements application data persistence by writing a file to disk.
 *
 * @package Curator\Persistence
 */
class FilePersistence implements PersistenceInterface {

  /**
   * FilePersistence constructor.
   *
   * @param \Curator\Persistence\FSAccessInterface $fs_access
   *   The filesystem access service.
   *
   * @param \Curator\Persistence\ReaderWriterLockInterface $lock
   *   The ReaderWriterLock service.
   */
  public function __construct(FSAccessInterface $fs_access, ReaderWriterLockInterface $lock, IntegrationConfig $integration_config) {

  }

}
