<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\CpkgReader;
use Curator\Cpkg\DeleteRenameBatchTask;
use Curator\FSAccess\FSAccessManager;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class DeleteRenameBatchTaskTest extends \PHPUnit_Framework_TestCase {
  protected function sutFactory() {
    $scheduler = $this->getMockBuilder('\\Curator\\Batch\\TaskScheduler')
      ->disableOriginalConstructor()
      ->setMethods(['removeGroupFromSession'])
      ->getMock();

    $rollback = $this->getMockBuilder('\\Curator\\Rollback\\RollbackCaptureService')
      ->disableOriginalConstructor()
      ->setMethods(['initializeCaptureDir', 'capture'])
      ->getMock();

    return new DeleteRenameBatchTask(
      new CpkgReader(),
      new FSAccessManager(new ReadAdapterMock('/'), new WriteAdapterMock('/')),
      $scheduler,
      $rollback
    );
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

  public function testUnrelatedRenamesAreParallelizable() {
    $this->assertTrue(
      $this->sutFactory()->isParallelizable($this->p('renames.zip'), 'parallelizable')
    );
  }

  public function testRenamedFileInRenamedDirectoryIsNotParallelizable() {
    $this->assertFalse(
      $this->sutFactory()->isParallelizable($this->p('renames.zip'), 'rename.after.renaming.dir')
    );
  }

  public function testRenamedFileInSubsequentlyRenamedDirectoryIsNotParallelizable() {
    $this->assertFalse(
      $this->sutFactory()->isParallelizable($this->p('renames.zip'), 'rename.before.renaming.dir')
    );
  }

  public function testRenamedFileToRenamedFileIsNotParallelizable() {
    $this->assertFalse(
      $this->sutFactory()->isParallelizable($this->p('renames.zip'), 'rename.aba'),
      'Renaming file A to A1, thus freeing A, and then renaming B to A, is not parallelizable.'
    );
  }

}
