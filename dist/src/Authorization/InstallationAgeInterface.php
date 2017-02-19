<?php


namespace Curator\Authorization;


use Curator\CuratorApplication;

interface InstallationAgeInterface {
  /**
   * @param string $curator_filename
   *   The filename of the file containing the first executed line of code.
   *   Obtainable via CuratorApplication::getCuratorFilename().
   *
   * @return int|bool
   */
  function getInstallationTime($curator_filename);
}
