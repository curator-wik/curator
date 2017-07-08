<?php


namespace Curator\Download;


use Curator\APIModel\v1\BatchRunnerRawProgressMessage;
use Curator\Batch\MessageCallbackRunnableInterface;
use Curator\IntegrationConfig;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

/**
 * Class CurlDownloadBatchRunnable
 *
 * Downloads the resource at a URL to a file as a single-runnable batch, but
 * sends download progress out to the client.
 *
 * @todo Investigate parallelizing with byte Range:ed requests?
 */
class CurlDownloadBatchRunnable extends AbstractRunnable implements MessageCallbackRunnableInterface {

  /**
   * @var float
   * Minimum seconds between % complete update messages.
   */
  const PROGRESS_UPDATE_INTERVAL_MS = 0.5;

  /**
   * @var IntegrationConfig $integration_config
   */
  protected $integration_config;

  /**
   * @var callable $progressMessageCallback
   */
  protected $progressMessageCallback;

  /**
   * @var string $url
   * The URL to be downloaded.
   */
  protected $url;

  /**
   * @var string $file_extension
   * An extension, including the 'dot', that the download filename
   * should end with.
   */
  protected $file_extension;

  /**
   * @var int
   * The microtime() of the last message sent.
   */
  protected $last_update_message_timestamp = 0;

  /**
   * @var int
   * Counts off every 4 calls to handleCurlProgress().
   */
  protected $handle_curl_progress_flap = 0;

  public function __construct(IntegrationConfig $integration_config, $id, $url, $file_extension = '') {
    parent::__construct($id);
    $this->integration_config = $integration_config;
    $this->url = $url;
    $this->file_extension = $file_extension;
  }

  public function setUpdateMessageCallback(callable $callback) {
    $this->progressMessageCallback = $callback;
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {
    // Get a handle to the file we'll put it in.
    list($filename, $fh) = $this->getFile();
    if ($fh === FALSE) {
      throw new \RuntimeException("Unable to open or create a new file to receive the download at " . $filename);
    }

    $ch = curl_init($this->url);
    curl_setopt($ch, CURLOPT_NOPROGRESS, FALSE);
    curl_setopt($ch, CURLOPT_FILE, $fh);
    if (is_callable($this->progressMessageCallback)) {
      curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'handleCurlProgress']);
    }

    if (curl_exec($ch) === FALSE) {
      $msg = sprintf("Error downloading \"%s\": %s",
        $this->url,
        function_exists('curl_strerror') ? curl_strerror(curl_errno($ch)) : 'cURL error code ' . curl_errno($ch)
      );
      curl_close($ch);
      throw new \RuntimeException($msg);
    }

    curl_close($ch);
    return $filename;
  }

  protected function getFile() {
    $hash = md5($this->integration_config->getSiteRootPath() . $this->url);
    $parts = pathinfo($this->url);
    $ext_from_url = array_key_exists('extension', $parts) ? $parts['extension'] : '';

    $name = sys_get_temp_dir() . DIRECTORY_SEPARATOR
      . $parts['filename'] . "_$hash"
      . ($this->file_extension ? $this->file_extension : ".$ext_from_url");

    $fh = fopen($name, 'w');
    return [$name, $fh];
  }

  protected function handleCurlProgress($resource,$download_size, $downloaded, $upload_size, $uploaded) {
    if ($download_size > 0) {
      // Calling microtime() is expensive, so don't do it on every invocation.
      $this->handle_curl_progress_flap = ($this->handle_curl_progress_flap + 1) % 4;
      if ($this->handle_curl_progress_flap === 0) {
        $now = microtime(true);
        if ($now - $this->last_update_message_timestamp >= self::PROGRESS_UPDATE_INTERVAL_MS) {
          $this->last_update_message_timestamp = $now;
          $message = new BatchRunnerRawProgressMessage();
          $message->pct = round(($downloaded / $download_size) * 100, 2);
          $this->progressMessageCallback($message);
        }
      }
    }
  }

}
