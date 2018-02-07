<?php
session_name('TESTAPP');
session_start();

switch ($_GET['action']) {
  case 'login':
    $_SESSION['isLoggedIn'] = true;
    echo 'Logged in.';
    break;
  case 'startCurator':
    if (! $_SESSION['isLoggedIn']) {
      http_response_code(401);
      echo '401 Unauthorized';
      exit;
    }
    session_write_close();

    /**
     * @var \Curator\AppManager $app_manager
     */
    $app_manager = require __DIR__ . '/../../../dist/web/index.php';
    $config = new \Curator\IntegrationConfig();
    $app_manager->applyIntegrationConfig($config);

    header('Location: /tests/Integration/fixtures/adjoining_application_integration.php/api/v1/status');
    break;
  default:
    echo 'This is the killer app!';
}

