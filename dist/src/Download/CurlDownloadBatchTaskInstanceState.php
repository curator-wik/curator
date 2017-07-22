<?php


namespace Curator\Download;


use Curator\Batch\TaskInstanceState;

class CurlDownloadBatchTaskInstanceState extends TaskInstanceState {
  protected $url;

  public function __construct($task_id, $url, $task_service_name = 'download.curl_download_task') {
    parent::__construct($task_service_name, $task_id, 1, 1);
    $this->url = $url;
  }

  public function getUrl() {
    return $this->url;
  }
}
