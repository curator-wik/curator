<?php
/**
 * @file
 * Makes a limited number of classes provided by the phar available to
 * integration scripts before they have chosen to call AppManager::run().
 */

/**
 * @var string $prefix
 *   The absolute path prefix for the sources.
 *
 *   Typically set in a closure by the phar stub; if not, assume we're running
 *   from a raw source tree and use the location of this file.
 */
if (!isset($prefix)) {
  $prefix = __DIR__;
}

// General types needed by all integration scripts
require_once "$prefix/src/AppManager.php";
include_once "$prefix/src/IntegrationConfig.php";

// Tasks
include_once "$prefix/src/TaskBuilder.php";
include_once "$prefix/src/Task/TaskInterface.php";
include_once "$prefix/src/Task/UpdateTask.php";

// App Targeting
include_once "$prefix/src/AppTargeting/TargeterInterface.php";
include_once "$prefix/src/AppTargeting/AbstractTargeter.php";
