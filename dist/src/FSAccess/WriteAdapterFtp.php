<?php

namespace Curator\FSAccess;


use Curator\Persistence\InvalidPersistedValueException;
use Curator\Persistence\PersistenceInterface;

class WriteAdapterFtp implements WriteAdapterInterface {

  protected $persistence;

  /**
   * WriteAdapterFtp constructor.
   *
   * @param \Curator\Persistence\PersistenceInterface $persistence
   * @param $password
   *   The password for the FTP connection.
   *
   * @throws \Curator\Persistence\InvalidPersistedValueException
   *   If the FTP server hostname has not been configured.
   */
  function __construct(PersistenceInterface $persistence, $password) {
    $this->persistence = $persistence;

    if (empty($this->persistence->get('ftp.server'))) {
      throw new InvalidPersistedValueException('The FTP server hostname has not been configured', 'ftp.server');
    }
  }

  public function getAdapterName() {
    return 'FTP';
  }

  public function filePutContents($filename, $data, $lock_if_able = TRUE) {

  }
}