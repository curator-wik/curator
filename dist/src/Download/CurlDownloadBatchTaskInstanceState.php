<?php


namespace Curator\Download;


use Curator\Batch\TaskInstanceState;

class CurlDownloadBatchTaskInstanceState extends TaskInstanceState {
  protected $url;

  public function __construct($task_id, $url, $task_service_name = 'download.curl_download_batch_task') {
    $this->parentConstruct($task_service_name, $task_id, 1, 1);
    $this->url = $url;
  }

  protected function parentConstruct($task_service_name, $task_id, $num_runners, $num_runnables) {
    parent::__construct($task_service_name, $task_id, $num_runners, $num_runnables);
  }

  public function getUrl() {
    return $this->url;
  }

  public function serialize() {
    $data = [
      'parent' => parent::serialize(),
      'url' => $this->getUrl()
    ];

    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    parent::unserialize($data['parent']);

    $this->url = $data['url'];
  }
}
