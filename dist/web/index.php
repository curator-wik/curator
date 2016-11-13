<?php
if (php_sapi_name() == 'cli') {
  die("Curator cannot be used from the command line.\n");
}

// Don't do < php 5.4. 5.3 has register_globals & safe mode ** shiver
if (!defined('PHP_VERSION_ID') || PHP_MAJOR_VERSION < 5 || (PHP_MAJOR_VERSION == 5 && PHP_MINOR_VERSION < 4)) {
  die("Curator requires PHP 5.4+\n");
}

require_once '../src/AppManager.php';
/**
 * @var \Curator\AppManager $app_manager
 */
$app_manager = \Curator\AppManager::singleton();

if (! $app_manager->isPhar()) {
  /*
   * Usually the phar stub bootstraps the AppManager, because it must be
   * returned to the application integration script that included the phar.
   * However, primarily to simplify development, don't force Curator to run from
   * a phar archive. We'll get things rolling now if no phar stub ran.
   *
   * Developers: to test different Curator modes on an unarchived source tree,
   * make copies of index.php and hit those. For example, to use standalone
   * mode, cp index.php curator.php. (Symlinks are resolved so won't work.)
   */
  $app_manager->determineRunMode(__FILE__);
  if ($app_manager->getRunMode() == \Curator\AppManager::RUNMODE_STANDALONE) {
    $app_manager->run();
    return;
  } else {
    return $app_manager;
  }
}
