<?php


namespace Curator\APIController\v1;
use Curator\APIModel\v1\StatusModel as APIStatus;
use Curator\Status;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class StatusController
 *   API endpoint for clients to hit when beginning a session.
 *
 *   Indicates any prerequisites such as authentication or missing settings
 *   that must be satisfied before code can be installed.
 */
class StatusController {
  /**
   * @var Status\StatusService $statusService
   */
  private $statusService;

  function __construct(Status\StatusService $statusService) {
    $this->statusService = $statusService;
  }

  public function handleRequest() {
    $status = $this->statusService->getStatus();
    $response = new APIStatus($status);
    return new JsonResponse($response);
  }
}
