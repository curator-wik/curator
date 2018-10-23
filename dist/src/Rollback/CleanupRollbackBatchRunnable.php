<?php


namespace Curator\Rollback;


use Curator\APIModel\v1\BatchRunnerRawProgressMessage;
use Curator\Batch\MessageCallbackRunnableInterface;
use Curator\FSAccess\FSAccessInterface;
use Curator\IntegrationConfig;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;
use Symfony\Component\Debug\Exception\ContextErrorException;

/**
 * Class CleanupRollbackBatchRunnable
 */
class CleanupRollbackBatchRunnable extends AbstractRunnable {

  /**
   * @var FSAccessInterface $fs_access
   */
  protected $fs_access;

  public function __construct($id, FSAccessInterface $fs_access) {
    parent::__construct($id);
    $this->fs_access = $fs_access;
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state)
  {
    /**
     * @var DoRollbackBatchTaskInstanceState $instance_state
     */
    $this->fs_access->rm($instance_state->getRollbackCapturePath(), TRUE);
  }
}
