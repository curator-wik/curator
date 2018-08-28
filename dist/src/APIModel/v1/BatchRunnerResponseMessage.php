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

  protected $num_runners;

  protected $num_runnables;

  protected $friendly_name;

  protected $task_group_id;

  protected $num_tasks_in_group;

  /**
   * BatchRunnerResponseMessage constructor.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The Response from the completed Task.
   * @param int[] $incomplete_runner_ids
   *   A list of runner ids used by any next enqueued task for the session.
   *   It has length 0 if there are no more enqueued tasks.
   * @param int $num_runners
   *   The maximum number of concurrent runners supported by the next enqueued task.
   *   It has value 0 if there are no more enqueued tasks.
   * @param string $friendly_name
   *   The friendly name of the next enqueued task, for UI.
   */
  public function __construct(Response $response, $incomplete_runner_ids, $num_runners, $num_runnables, $friendly_name, $task_group_id, $num_tasks_in_group) {
    $this->type = BatchRunnerMessage::TYPE_RESPONSE;
    $this->response = $response;
    $this->incomplete_runner_ids = $incomplete_runner_ids;
    $this->num_runners = $num_runners;
    $this->num_runnables = $num_runnables;
    $this->friendly_name = $friendly_name;
    $this->task_group_id = $task_group_id;
    $this->num_tasks_in_group = $num_tasks_in_group;
  }

  public function toJson() {
    $data = [
      'type' => $this->type,
      'incomplete_runner_ids' => $this->incomplete_runner_ids,
      'num_runners' => $this->num_runners,
      'numRunnables' => $this->num_runnables,
      'taskGroupId' => $this->task_group_id,
      'friendlyName' => $this->friendly_name,
      'numTasksInGroup' => $this->num_tasks_in_group,
      'headers' => $this->response->headers->all(),
      'body' => $this->response->getContent()
    ];
    return json_encode($data);
  }
}
