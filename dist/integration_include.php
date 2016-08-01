<?php
/**
 * @file
 * Makes a limited number of classes provided by the phar available to
 * integration scripts before they have chosen to call AppManager::run().
 */

// General types needed by all integration scripts
require_once 'phar://curator/src/AppManager.php';
include_once 'phar://curator/src/IntegrationConfig.php';

// Tasks
include_once 'phar://curator/src/TaskBuilder.php';
include_once 'phar://curator/src/Task/TaskInterface.php';
include_once 'phar://curator/src/Task/UpdateTask.php';

// App Targeting
include_once 'phar://curator/src/AppTargeting/TargeterInterface.php';
include_once 'phar://curator/src/AppTargeting/AbstractTargeter.php';
