<?php


namespace Curator;

use Curator\Controller\SinglePageHostController;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class CuratorApplication extends Application {

  /**
   * @var IntegrationConfig $integrationConfig
   *   Configuration from an application integration script, if present.
   */
  protected $integrationConfig;

  public function __construct(IntegrationConfig $integration_config, AppManager $app_manager) {
    parent::__construct();
    $this->integrationConfig = $integration_config;

    $this['app_manager'] = $app_manager;

    $this->prepareRoutes();

    /**
     * When no other route matches, see if it is a file under web/curator-gui
     */
    $this->error(function(NotFoundHttpException $e, Request $request) {
      $file_response = SinglePageHostController::serveStaticFile($request);
      if ($file_response) {
        return $file_response;
      } else {
        return NULL;
      }
    }, -4);
  }

  protected function prepareRoutes() {
    $this->get('/', '\Curator\Controller\SinglePageHostController::generateSinglePageHost');
  }
}
