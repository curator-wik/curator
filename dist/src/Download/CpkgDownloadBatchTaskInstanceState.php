<?php


namespace Curator\Download;


use Curator\Batch\TaskInstanceState;

class CpkgDownloadBatchTaskInstanceState extends CurlDownloadBatchTaskInstanceState {
  /**
   * @var string $rollback_capture_dir
   */
  protected $rollback_capture_dir;

  public function __construct($task_id, $url, $rollback_capture_dir, $task_service_name = 'download.curl_download_batch_task') {
    $this->parentConstruct($task_service_name, $task_id, 2, 2);
    $this->url = $url;
    $this->rollback_capture_dir = $rollback_capture_dir;
  }

  public function getRollbackCaptureDir() {
    return $this->rollback_capture_dir;
  }

  public function serialize() {
    $data = [
      'parent' => parent::serialize(),
      'rollback' => $this->getRollbackCaptureDir()
    ];

    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    parent::unserialize($data['parent']);

    $this->rollback_capture_dir = $data['rollback'];
  }
}
