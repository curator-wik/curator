<?php
namespace Curator\APIModel\v1;

use Symfony\Component\HttpFoundation\Response;

/**
 * Class BatchRunnerResponseMessage
 *   The final message sent on the chunked response socket, encodes the
 *   headers and body of a Response in a JSON object, in effect enabling a
 *   response nested in a response...if the client knows how to interpret it.
 *
 *   This is used to return the full details of a Response to the client when
 *   the original request triggered a long-running batch process.
 */
class BatchRunnerResponseMessage extends BatchRunnerMessage {
  /**
   * @var Response $response
   */
  protected $response;

  protected $incomplete_runner_ids;

  /**
   * BatchRunnerResponseMessage constructor.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The Response from the completed Task.
   * @param int[] $incomplete_runner_ids
   *   A list of runner ids used by any next enqueued task for the session.
   */
  public function __construct(Response $response, $incomplete_runner_ids) {
    $this->type = BatchRunnerMessage::TYPE_RESPONSE;
    $this->response = $response;
    $this->incomplete_runner_ids = $incomplete_runner_ids;
  }

  public function toJson() {
    $data = [
      'type' => $this->type,
      'incomplete_runner_ids' => $this->incomplete_runner_ids,
      'headers' => $this->response->headers->all(),
      'body' => $this->response->getContent()
    ];
    return json_encode($data);
  }
}
