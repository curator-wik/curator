<?php


namespace Curator\Task\Decoder;


use Curator\Persistence\PersistenceInterface;
use Curator\Task\ParameterlessTask;
use Curator\Task\TaskDecoderInterface;
use Curator\Task\TaskInterface;

class InitializeHmacSecret implements TaskDecoderInterface {
  /**
   * @var string $putative_secret
   *   The generated shared secret is only here until it has been committed.
   */
  protected $putative_secret;

  /**
   * @var PersistenceInterface $persistence
   */
  protected $persistence;

  public function __construct(PersistenceInterface $persistence) {
    $this->persistence = $persistence;
  }

  protected function generateRandomBytes($length) {
    // We autoload paragonie/random_compat.
    return random_bytes($length);
  }

  public function decodeTask(TaskInterface $task) {
    /**
     * @var ParameterlessTask $task
     */
    if ($task->getTaskNumber() === ParameterlessTask::TASK_GEN_INTEGRATION_SECRET) {
      $this->putative_secret = bin2hex($this->generateRandomBytes(64));
      return $this->putative_secret;
    } else if ($task->getTaskNumber() === ParameterlessTask::TASK_COMMIT_INTEGRATION_SECRET) {
      $this->persistence->beginReadWrite();
      $this->persistence->set('adjoining_app_hmac_secret', $this->putative_secret);
      $this->persistence->end();
      return TRUE;
    } else {
      throw new \LogicException('The InitializeHmacSecret task decoder was asked to perform an unknown task number.');
    }
  }
}
