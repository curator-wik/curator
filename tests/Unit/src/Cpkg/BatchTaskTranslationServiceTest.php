<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\BatchTaskTranslationService;
use Curator\Cpkg\CpkgReader;
use Curator\Tests\Shared\Mocks\AppTargeterMock;

class BatchTaskTranslationServiceTest extends \PHPUnit_Framework_TestCase {

  protected function sutFactory() {
    $sut = new BatchTaskTranslationService(
      new AppTargeterMock(),
      new CpkgReader()
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

  /**
   * No assertions currently, but the absence of exceptions is worth testing.
   */
  public function testMinimalValidCpkg() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('minimal-valid-cpkg.zip'));
  }


}
