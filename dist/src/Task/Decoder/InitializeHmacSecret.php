<?php


namespace Curator\Task\Decoder;


use Curator\Task\TaskDecoderInterface;
use Curator\Task\TaskInterface;

class InitializeHmacSecret implements TaskDecoderInterface {
  protected function generateRandomBytes($length) {
    return random_bytes($length);
  }

  public function decodeTask(TaskInterface $task) {
    return bin2hex($this->generateRandomBytes(64));
  }
}
