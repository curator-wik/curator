<?php


namespace Curator\Task\Decoder;


use Curator\Persistence\PersistenceInterface;
use Curator\Task\ParameterlessTask;
use Curator\Task\TaskDecoderInterface;
use Curator\Task\TaskInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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

  /**
   * @var SessionInterface $session
   */
  protected $session;

  public function __construct(PersistenceInterface $persistence, SessionInterface $session) {
    $this->persistence = $persistence;
    $this->session = $session;
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
      try {
        $this->persistence->beginReadWrite();
        $this->persistence->set('adjoining_app_hmac_secret', $this->putative_secret);
        $this->persistence->end();
        return TRUE;
      } catch (\Exception $e) {
        // A working persistence mechanism may not be configured yet.
        // In this case, persist the secret to the session for now.
        if ($this->session->start()) {
          $this->session->set('adjoining_app_hmac_secret', $this->putative_secret);
          $this->session->save();
          return TRUE;
        } else {
          return FALSE;
        }
      }
    } else {
      throw new \LogicException('The InitializeHmacSecret task decoder was asked to perform an unknown task number.');
    }
  }
}
