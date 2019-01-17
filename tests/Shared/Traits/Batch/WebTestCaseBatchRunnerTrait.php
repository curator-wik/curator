<?php


namespace Curator\Tests\Shared\Traits\Batch;


use Curator\APIModel\v1\BatchRunnerMessage;
use Curator\Batch\TaskGroup;
use Curator\Batch\TaskGroupManager;
use Symfony\Component\HttpKernel\Client;

trait WebTestCaseBatchRunnerTrait {
  private $ENDPOINT_BATCH_RUNNER = '/api/v1/batch/runner';
  private $ENDPOINT_BATCH_TASK_INFO = '/api/v1/batch/current-task';

  protected function runBatchTasks(Client $client, TaskGroup $taskgroup, $run_subsequent_tasks = TRUE, $allow_runnable_failures = FALSE) {
    $prev_task = NULL;
    // Seed the requests to the batch controller by asking it for the current task information.
    $client->request('GET', $this->ENDPOINT_BATCH_TASK_INFO);
    $task_info_response = $client->getResponse();
    $batch_info_response = json_decode($task_info_response->getContent(), TRUE);
    $this->assertTrue(is_array($batch_info_response));
    $this->assertCount(0, array_diff_key([
      'friendlyName' => '',
      'runnerIds' => '',
      'numRunners' => '',
      'numRunnables' => '',
      'taskGroupId' => '',
      'numTasksInGroup' => ''], $batch_info_response));
    $incomplete_runner_ids = $batch_info_response['runnerIds'];

    $runner_request_count = 0;
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
      $runner_request_count++;

      if (! $allow_runnable_failures) {
        // Ensure no errors reported in update messages.
        foreach ($messages as $message) {
          if ($message->type === BatchRunnerMessage::TYPE_UPDATE) {
            $this->assertTrue(
              $message->ok,
              sprintf('BatchRunnerUpdateMessage indicated failure: %s', implode(' | ', $message->chatter ? $message->chatter : []))
            );
          }
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

    return $runner_request_count;
  }

  protected function decodeBatchResponseContent($content) {
    // This method tests both that the response content as a whole is valid json,
    // and that each independent chunk with minimal massaging is valid json.
    // This ensures that both clients choosing to buffer and process the complete request
    // and clients choosing to process streamed chunks will receive valid data.
    $complete_buffer = '';
    $objects = [];
    while(strlen($content)) {
      list($chunk_len, $content) = explode("\r\n", $content, 2);
      $chunk_len = hexdec($chunk_len);
      $chunk = substr($content, 0, $chunk_len);
      $complete_buffer .= $chunk;

      // Remove things from the individual chunk that are there to make valid overall response json;
      // the result should be a mini valid json.
      // 1. All chunks except the last one begin with one unused character, either "[" or ","
      // 2. The last chunk is an unused terminating ] and should be ignored.
      $content = substr($content, $chunk_len + strlen("\r\n"));
      $is_last_chunk = strlen($content) == 0;
      $chunk = substr($chunk, $is_last_chunk ? 0 : 1);
      if ($is_last_chunk) {
        $this->assertEquals(']', $chunk);
      } else {
        $object = json_decode($chunk);
        if ($object === null) {
          throw new \RuntimeException('Response chunk was not valid json.');
        }
        $objects[] = json_decode($chunk);
      }
    }

    if (json_decode($complete_buffer) === null) {
      throw new \RuntimeException('Overall batch runner response was not valid json.');
    }

    return $objects;
  }
}