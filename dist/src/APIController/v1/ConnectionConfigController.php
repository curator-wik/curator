<?php


namespace Curator\APIController\v1;


use Swagger\Annotations\Swagger;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ConnectionConfigController {
  /**
   * @param string $connectionType
   *   'ftp', 'ssh', etc.
   *
   * @SWG\Post(
   *   path="/config/connection/ftp",
   *   @SWG\Parameter(
   *     name="body",
   *     in="body",
   *     description="FTP connection configuration object",
   *     required=true,
   *     @SWG\Schema(ref="#/definitions/FtpConfig"),
   *   ),
   *   @SWG\Response(
   *     response=405,
   *     description="Invalid input"
   *   ),
   *   @SWG\Response(
   *     response=403,
   *     description="Unauthorized"
   *   )
   * )
   */
  public function handlePost($connectionType) {
    $camelType = ucfirst($connectionType);
    if (! class_exists('\\Curator\\APIModel\\v1\\ConnectionConfig\\' . $camelType)) {
      throw new NotFoundHttpException();
    }

    echo "$connectionType! Good choice!";
  }
}
