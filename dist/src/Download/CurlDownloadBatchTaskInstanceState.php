<?php


namespace Curator\Download;


use Curator\Batch\TaskInstanceState;

class CurlDownloadBatchTaskInstanceState extends TaskInstanceState {
  protected $url;

  public function __construct($task_id, $url) {
    parent::__construct('download.curl_download_task', $task_id, 1, 1);
    $this->url = $url;
  }

  public function getUrl() {
    return $this->url;
  }
}
