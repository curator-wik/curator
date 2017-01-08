<?php


namespace Curator\APIController\v1;
use Curator\APIModel\v1\StatusModel;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class StatusController
 *   API endpoint for clients to hit when beginning a session.
 *
 *   Indicates any prerequisites such as authentication or missing settings
 *   that must be satisfied before code can be installed.
 */
class StatusController {
  public function handleRequest() {
    $status = new StatusModel();
    return new JsonResponse($status);
  }
}
