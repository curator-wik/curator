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

  public function __construct(Response $response) {
    $this->type = BatchRunnerMessage::TYPE_RESPONSE;
  }

  public function toJson() {
    $data = [
      'type' => $this->type,
      'headers' => $this->response->headers->all(),
      'body' => $this->response->getContent()
    ];
    return json_encode($data);
  }
}
