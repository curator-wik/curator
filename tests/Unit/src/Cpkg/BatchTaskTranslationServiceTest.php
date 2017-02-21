<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Tests\Shared\Mocks\AppTargeterMock;

class BatchTaskTranslationServiceTest extends \PHPUnit_Framework_TestCase {

  protected function sutFactory() {
    $sut = new BatchTaskTranslationService(
      new AppTargeterMock()
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
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage internal corruption of phar
   */
  public function testNonArchiveFormatIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('not-an-archive.test'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage internal corruption of phar
   */
  public function testNonArchiveFormatIsRejected_2() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('not-an-archive.tar'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage cpkg is invalid
   */
  public function testNonCpkgIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('not-a-cpkg.tar'));
  }
}
