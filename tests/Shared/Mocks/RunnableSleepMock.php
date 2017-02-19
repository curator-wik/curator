<?php


namespace Curator\Tests\Shared\Mocks;


use Curator\Batch\AbstractRunnable;
use Curator\Batch\TaskInterface;

class RunnableSleepMock extends AbstractRunnable {
  /**
   * @var int $ms_sleep
   */
  protected $ms_sleep;

  public function __construct(\Curator\Batch\TaskInterface $parent_task, $runnable_id, $ms_sleep) {
    parent::__construct($parent_task, $runnable_id);
    $this->ms_sleep = $ms_sleep;
  }

  public function run() {
    if ($this->ms_sleep == 0) {
      return;
    }
    usleep($this->ms_sleep * 1000);
  }
}
