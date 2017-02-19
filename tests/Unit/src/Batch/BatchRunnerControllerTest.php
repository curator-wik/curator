<?php


namespace mbaynton\Tests\Unit\Batch;


use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class BatchRunnerControllerTest extends \PHPUnit_Framework_TestCase {
  protected function sutFactory() {
    $session_storage = new MockArraySessionStorage();
    $session_storage->setId(__CLASS__);
    $session = new Session($session_storage);

    // Set session's BatchTaskQueue.
    $num_queued_tasks = 1;
    $batch_taskId_queue = [];
    for ($i = 1; $i <= $num_queued_tasks; $i++) {
      $batch_taskId_queue[] = $i;
    }
    $session->set('BatchTaskQueue', $batch_taskId_queue);

    $persistence = new InMemoryPersistenceMock();

    // Set and persist serialized Task for the Task ids.

    $persistence->loadMockedData([

    ]);
  }

  public function testNothing() {

  }
}
