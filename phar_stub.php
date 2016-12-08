<?php
/*
 * Curator: Web Installation Kit.
 *
 * (c) 2016 Michael Baynton <mike@mbaynton.com>
 *
 * Licensed under the MIT License.
 */

use \Curator\AppManager;

$web = '/web/index.php';

if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', 0)) {
  function load_integration_resources() {
    $prefix = 'phar://curator';
    require_once 'phar://curator/integration_include.php';
  }
  load_integration_resources();

  $app_manager = AppManager::singleton();
  $app_manager->setIsPhar();
  // in phar stub, basename(__FILE__) == name of archive file
  $app_manager->determineRunMode(__FILE__);

  if ($app_manager->getRunMode() == AppManager::RUNMODE_STANDALONE) {
    $app_manager->run();
    return;
  } else {
    return $app_manager;
  }

} else {
  // TODO: Revisit support sans stream wrapper, run Phar::createDefaultStub
  header('HTTP/1.1 500 Internal Error');
  echo "<html>\n<head>\n<title>Error</title>\n</head>\n<body>\nERROR: Cannot run <em>Curator</em> because this server's php does not support the phar format.\n</body>\n</html>";
  return;
}
__HALT_COMPILER(); ?>
