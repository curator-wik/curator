<?php
/**
 * @file A minimal direct access script portion of an app integration for tests.
 * @see docs/Integration-HOWTO.md
 */

/**
 * @var \Curator\AppManager $app_manager
 */
$app_manager = require __DIR__ . '/../../../dist/web/index.php';
$app_manager->run();
