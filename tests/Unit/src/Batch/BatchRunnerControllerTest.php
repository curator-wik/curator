<?php


namespace mbaynton\Tests\Unit\Batch;


use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class BatchRunnerControllerTest extends \PHPUnit\Framework\TestCase {
  protected function sutFactory() {
    $session_storage = new MockArraySessionStorage();
    $session_storage->setId(__CLASS__);
    $session = new Session($session_storage);

    $persistence = new InMemoryPersistenceMock();

    // Set and persist serialized Task for the Task ids.

    $persistence->loadMockedData([

    ]);
  }

  public function testNothing() {

  }
}
