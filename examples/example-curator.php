<?php
/**
 * @file curator.php
 * Example for the skeleton of an integration script. This shows how you would
 * specify a task for Curator to perform, and then launch the application.
 *
 * Your file should not be named 'curator.php', or Curator will run in
 * standalone, not embedded, mode.
 */

use Curator\AppManager;
use Curator\IntegrationConfig;

$closure = function() {
  /**
   * @var AppManager $app_manager
   */
  $app_manager = require './curator.phar';

  $config = new IntegrationConfig();
  $config->taskIs()
    ->update('backdrop')
    ->fromPackage('file://site/default/files/...');

  $app_manager->applyIntegrationConfig($config);

  $app_manager->run();
};
$closure();
