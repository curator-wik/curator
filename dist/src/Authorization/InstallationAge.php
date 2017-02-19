<?php


namespace Curator\Authorization;


use Curator\CuratorApplication;

class InstallationAge implements InstallationAgeInterface {
  public function getInstallationTime($curator_filename) {
    // FSAccessInterface doesn't currently have stat(), and going through it
    // probably wouldn't add value to this end.
    try {
      $install_stat = stat($curator_filename);
    } catch (\ErrorException $e) {
      return FALSE;
    }

    return max($install_stat['ctime'], $install_stat['mtime']);
  }
}
