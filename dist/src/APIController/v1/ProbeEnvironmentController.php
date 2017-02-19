<?php


namespace Curator\APIController\v1;
use Curator\APIModel\v1\StatusModel;
use Curator\Status\StatusService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class ProbeEnvironmentController
 *   API Endpoint for initiating a server-side environment probe.
 */
class ProbeEnvironmentController {

  /**
   * @var StatusService $statusService
   */
  protected $statusService;

  public function __construct(StatusService $statusService) {
    $this->statusService = $statusService;
  }

  public function handleRequest() {
    // Environment probe is only allowed on new/unconfigured installations
    if ($this->statusService->getStatus()->is_configured) {
      throw new AccessDeniedHttpException('Environment probe is only permitted on new installations.');
    }

    // The environment probe is a concurrent batch

  }
}
