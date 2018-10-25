<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\AppTargeting\AppDetector;
use Curator\Batch\TaskGroupManager;
use Curator\Batch\TaskScheduler;
use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Cpkg\CpkgBatchTaskInstanceState;
use Curator\Cpkg\CpkgClassificationService;
use Curator\Cpkg\CpkgReader;
use Curator\Cpkg\DeleteRenameBatchTask;
use Curator\FSAccess\FSAccessManager;
use Curator\IntegrationConfig;
use Curator\Status\StatusModel;
use Curator\Tests\Shared\Mocks\AppTargeterMock;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class BatchTaskTranslationServiceTest extends \PHPUnit_Framework_TestCase {

  /**
   * @var CpkgReader $reader
   *   The CpkgReader for the currently running test.
   */
  protected $reader;

  /**
   * @var InMemoryPersistenceMock $persistence
   *   The persistence for the currently running test.
   */
  protected $persistence;

  /**
   * @var TaskScheduler $task_scheduler
   *   The task scheduler for the currently running test.
   */
  protected $task_scheduler;

  /**
   * @var TaskGroupManager $taskgroup_manager
   *   The task group manager for the currently running test.
   */
  protected $taskgroup_manager;

  protected function setUp() {
    parent::setUp();

    $this->reader = new CpkgReader();
    $this->persistence = new InMemoryPersistenceMock();
    $this->task_scheduler = $this->getMockBuilder('\Curator\Batch\TaskScheduler')->disableOriginalConstructor()->getMock();

    $this->taskgroup_manager = $this->getMockBuilder('\Curator\Batch\TaskGroupManager')
      ->setConstructorArgs([$this->persistence, $this->task_scheduler])
      ->enableProxyingToOriginalMethods()
      ->getMock();
  }

  protected function sutFactory() {

    $detector = $this->getMockBuilder('\Curator\AppTargeting\AppDetector')
      ->disableOriginalConstructor()
      ->setMethods(['getTargeter'])
      ->getMock();
    $detector->method('getTargeter')->willReturn(new AppTargeterMock());

    $status = $this->getMockBuilder('\\Curator\\Status\\StatusService')
      ->disableOriginalConstructor()
      ->setMethods(['getStatus'])
      ->getMock();
    $statusModel = new StatusModel();
    $statusModel->rollback_capture_path = '/test-rollback-path';
    $status->method('getStatus')
      ->willReturn($statusModel);

    $classifier = new CpkgClassificationService($this->reader);

    $sut = new BatchTaskTranslationService(
      $status,
      $detector,
      $this->reader,
      $this->taskgroup_manager,
      $this->task_scheduler,
      $this->persistence,
      $classifier
    );

    return $sut;
  }

  /**
   * @param $archive_name
   *   Name of a file in the cpkgs fixtures directory.
   * @return string
   *   Full path to the file.
   */
  protected function p($archive_name) {
    return __DIR__ . "/../../fixtures/cpkgs/$archive_name";
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage The update package is for "Nothing", but you are running MockApp.
   */
  public function testWrongApplicationIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('wrong-application.zip'));
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage The update package provides version "1.2.3", but it is already installed.
   */
  public function testAlreadyAppliedVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('already-applied-version.zip'));
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage The update package does not contain updates to your version of MockApp. You are running version 1.2.3; the package updates version 1.2.1.
   */
  public function testWrongVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('wrong-version-single.zip'));
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage the package updates versions 1.2.0 through 1.2.1.
   */
  public function testWrongVersionsIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('wrong-version-multiple.zip'));
  }

  public function testMinimalValidCpkg() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('minimal-valid-cpkg.zip'));

    $this->taskgroup_manager->expects($this->never())->method('appendTaskInstance');
  }

  public function testDeletionsCauseDeleteRenameTask() {
    $sut = $this->sutFactory();
    $this->taskgroup_manager->expects($this->once())
      ->method('appendTaskInstance')
      ->with(
        $this->isInstanceOf('\Curator\Batch\TaskGroup'),
        $this->callback(function(CpkgBatchTaskInstanceState $instanceState) {
          return $instanceState->getTaskServiceName() == 'cpkg.delete_rename_batch_task';
        })
      );

    $sut->makeBatchTasks($this->p('minimal+deletion.zip'));
  }

  public function testRenamesCauseDeleteRenameTask() {
    $sut = $this->sutFactory();
    $this->taskgroup_manager->expects($this->once())
      ->method('appendTaskInstance')
      ->with(
        $this->isInstanceOf('\Curator\Batch\TaskGroup'),
        $this->callback(function(CpkgBatchTaskInstanceState $instanceState) {
          return $instanceState->getTaskServiceName() == 'cpkg.delete_rename_batch_task';
        })
      );

    $sut->makeBatchTasks($this->p('minimal+renames.zip'));
  }

}
