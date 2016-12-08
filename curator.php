<?php
/**
 * @file curator.php
 * Fallback entry point for .phar distributions, when server does not support
 * interpreting .phar files directly.
 */

use Curator\AppManager;
use Curator\IntegrationConfig;

$closure = function() {
  /**
   * @var AppManager $app_manager
   */
  $app_manager = require './backdrop-curator.phar';

  $config = new IntegrationConfig();
  $config->taskIs()
    ->update('backdrop')
    ->fromPackage('file://site/default/files/...');

  $app_manager->applyConfiguration($config);

  $app_manager->run();
};
$closure();
