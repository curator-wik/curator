<?php
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * @var \Curator\AppManager $app_manager
 */
$app_manager = \Curator\AppManager::create();

/*
 * Usually the phar stub bootstraps the AppManager, because it must be
 * returned to the application integration script that included the phar.
 * However, primarily to simplify development, don't force Curator to run from
 * a phar archive. This file can also get things rolling if no phar stub ran.
 *
 * Developers: to test different Curator modes on an unarchived source tree,
 * make copies of index.php and hit those. For example, to use standalone
 * mode, cp index.php curator.php. (Symlinks are resolved so won't work.)
 */
if ($app_manager->getRunMode() == \Curator\AppManager::RUNMODE_UNSET) {
  $app_manager->determineRunMode(__FILE__);
}
if ($app_manager->getRunMode() == \Curator\AppManager::RUNMODE_STANDALONE) {
  $app_manager->run();
  return;
} else {
  return $app_manager;
}

