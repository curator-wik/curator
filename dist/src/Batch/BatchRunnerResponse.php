<?php


namespace Curator\Batch;


use Curator\APIModel\v1\BatchRunnerMessage;
use Symfony\Component\HttpFoundation\Response;

class BatchRunnerResponse extends Response {

  protected $needs_flush = FALSE;

  protected $is_test = FALSE;

  public function __construct(array $messages = []) {
    parent::__construct('', 200, ['Transfer-Encoding' => 'chunked']);

    // If in a phpunit test, do not print / echo things.
    if (getenv('PHPUNIT-TEST') == '1') {
      $this->is_test = TRUE;
    }

    foreach ($messages as $message) {
      $this->postMessage($message);
    }
  }

  public function postMessage(BatchRunnerMessage $message) {
    if (! headers_sent()) {
      // Force headers to go out before giving PHP any response body data.
      $this->sendHeaders();
      flush();
    }

    $chunk = $message->toJson();

    if ($this->is_test) {
      $this->setContent($this->getContent() . sprintf("%x\r\n%s\r\n", strlen($chunk), $chunk));
    } else {
      printf("%x\r\n%s\r\n", strlen($chunk), $chunk);
    }
    $this->needs_flush = TRUE;
  }

  public function flush() {
    if ($this->needs_flush) {
      flush();
      $this->needs_flush = FALSE;
    }
  }

  public function sendContent() {
    if ($this->is_test) {
      return parent::sendContent();
    } else {
      $this->flush();
      return $this;
    }
  }
}
