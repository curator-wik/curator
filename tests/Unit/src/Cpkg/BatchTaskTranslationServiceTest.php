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
   * @expectedException \Symfony\Component\HttpFoundation\File\Exception\FileException
   * @expectedExceptionMessage Archive at baz.tar does not exist or is empty.
   */
  public function testInvalidPathToArchive() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks('baz.tar');
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

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in application file is corrupt or unsupported
   */
  public function testEmptyApplicationFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('empty-application-file.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in version file is corrupt or unsupported
   */
  public function testInvalidVersionFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('invalid-version.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in prev-versions-inorder file is corrupt or unsupported
   */
  public function testEmptyPrevVersionsFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('empty-prev-versions.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in prev-versions-inorder file is corrupt or unsupported
   */
  public function testInvalidPrevVersionsFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('invalid-prev-versions.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Update payload directory "payload/1.2.4" is absent.
   */
  public function testMissingPayloadVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('missing-payload-version.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Update payload directory "payload/1.2.4" is not a directory.
   */
  public function testNondirectoryPayloadVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->makeBatchTasks($this->p('nondirectory-payload-version.zip'));
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
