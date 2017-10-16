<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\CpkgReader;

class CpkgReaderTest extends \PHPUnit\Framework\TestCase {
  protected function sutFactory() {
    return new CpkgReader();
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
    $sut->validateCpkgStructure('baz.tar');
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage internal corruption of phar
   */
  public function testNonArchiveFormatIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('not-an-archive.test'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage internal corruption of phar
   */
  public function testNonArchiveFormatIsRejected_2() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('not-an-archive.tar'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage cpkg is invalid
   */
  public function testNonCpkgIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('not-a-cpkg.tar'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in application file is corrupt or unsupported
   */
  public function testEmptyApplicationFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('empty-application-file.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in version file is corrupt or unsupported
   */
  public function testInvalidVersionFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('invalid-version.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in prev-versions-inorder file is corrupt or unsupported
   */
  public function testEmptyPrevVersionsFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('empty-prev-versions.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Data in prev-versions-inorder file is corrupt or unsupported
   */
  public function testInvalidPrevVersionsFileIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('invalid-prev-versions.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Update payload directory "payload/1.2.4" is absent.
   */
  public function testMissingPayloadVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('missing-payload-version.zip'));
  }

  /**
   * @expectedException \UnexpectedValueException
   * @expectedExceptionMessage Update payload directory "payload/1.2.4" is not a directory.
   */
  public function testNondirectoryPayloadVersionIsRejected() {
    $sut = $this->sutFactory();
    $sut->validateCpkgStructure($this->p('nondirectory-payload-version.zip'));
  }

  public function testRenamedFileParser() {
    // TODO
  }

  public function testDeletedFileParser() {
    // TODO
  }
  
}
