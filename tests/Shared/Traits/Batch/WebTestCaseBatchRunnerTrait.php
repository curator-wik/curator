<?php


namespace Curator\Tests\Shared\Traits\Batch;


use Curator\APIModel\v1\BatchRunnerMessage;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Symfony\Component\HttpKernel\Client;

trait WebTestCaseBatchRunnerTrait {
  private $ENDPOINT_BATCH_RUNNER = '/api/v1/batch/runner';

  protected function runBatchTasks(Client $client, TaskGroup $taskgroup, $run_subsequent_tasks = TRUE) {
    /**
     * @var TaskGroupManager $taskgroup_manager
     */
    $taskgroup_manager = $this->app['batch.taskgroup_manager'];

    $prev_task = NULL;
    // Seed the requests to the batch controller by looking up the runner ids
    // of the first Task. In real world, will need to be pulled from an API.
    $curr_task = $taskgroup_manager->getActiveTaskInstance($taskgroup);
    $incomplete_runner_ids = $curr_task->getRunnerIds();
    $this->assertGreaterThan(0, count($incomplete_runner_ids));

    while (count($incomplete_runner_ids)) {
      shuffle($incomplete_runner_ids);
      $runner_id = reset($incomplete_runner_ids);
      $client->request('POST', $this->ENDPOINT_BATCH_RUNNER,
        [],
        [],
        [
          'HTTP_X-Runner-Id' => $runner_id
        ]);

      $response = $client->getResponse();
      $messages = $this->decodeBatchResponseContent($response->getContent());

      // Ensure no errors reported in update messages.
      foreach ($messages as $message) {
        if ($message->type === BatchRunnerMessage::TYPE_UPDATE) {
          $this->assertTrue(
            $message->ok,
            sprintf('BatchRunnerUpdateMessage indicated failure: %s', implode(' | ', $message->chatter ? $message->chatter : []))
          );
        }
      }

      $last_message = end($messages);
      if ($last_message->type == BatchRunnerMessage::TYPE_CONTROL) {
        $incomplete_runner_ids = $last_message->incomplete_runner_ids;
      } else if ($last_message->type == BatchRunnerMessage::TYPE_RESPONSE) {
        if ($run_subsequent_tasks) {
          $incomplete_runner_ids = $last_message->incomplete_runner_ids;
        } else {
          $incomplete_runner_ids = [];
        }
      } else {
        throw new \RuntimeException('Last message in batch response was not TYPE_RESPONSE or TYPE_CONTROL.');
      }
    }
  }

  protected function decodeBatchResponseContent($content) {
    $objects = [];
    while(strlen($content)) {
      list($chunk_len, $content) = explode("\r\n", $content, 2);
      $chunk_len = hexdec($chunk_len) + strlen("\r\n"); // incl. trailing \r\n
      $chunk = substr($content, 0, $chunk_len);
      $objects[] = json_decode($chunk);
      $content = substr($content, $chunk_len);
    }
    return $objects;
  }
}