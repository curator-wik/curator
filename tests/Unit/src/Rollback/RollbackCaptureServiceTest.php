<?php


namespace Curator\Tests\Unit\Rollback;


use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\ChangeTypeDelete;
use Curator\Rollback\ChangeTypePatch;
use Curator\Rollback\ChangeTypeRename;
use Curator\Rollback\ChangeTypeWrite;
use Curator\Rollback\RollbackCaptureService;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class RollbackCaptureServiceTest extends \PHPUnit_Framework_TestCase
{
  const PROJECT_PATH = '/within/a/project';
  const ROLLBACK_CAPTURE_PATH = 'private/rollback';

  /**
   * @var ReadAdapterMock $readAdapter
   */
  protected static $readAdapter;

  /**
   * @var WriteAdapterMock $writeAdapter
   */
  protected static $writeAdapter;

  /**
   * @var FSAccessManager $fsAccessManager
   */
  protected static $fsAccessManager;

  public static function setUpBeforeClass()
  {
    parent::setUpBeforeClass();
    self::$readAdapter = new ReadAdapterMock(self::PROJECT_PATH);
    self::$writeAdapter = new WriteAdapterMock(self::PROJECT_PATH);
    self::$fsAccessManager = new FSAccessManager(self::$readAdapter, self::$writeAdapter);
  }

  public function setUp()
  {
    parent::setUp();
    // Make fresh filesystem before each test.
    $contents = new MockedFilesystemContents();
    self::$readAdapter->setFilesystemContents($contents);
    self::$writeAdapter->setFilesystemContents($contents);

    self::$fsAccessManager->setWorkingPath(self::PROJECT_PATH);
    self::$fsAccessManager->setWriteWorkingPath(self::PROJECT_PATH);
  }

  protected static function sutFactory() {
    $sut = new RollbackCaptureService(self::$fsAccessManager);
    $sut->initializeCaptureDir(self::ROLLBACK_CAPTURE_PATH);
    return $sut;
  }

  public function testSingleDelete() {
    $sut = self::sutFactory();
    $this->assertTrue(self::$fsAccessManager->isFile('README'));
    $contents = self::$fsAccessManager->fileGetContents('README');
    $sut->capture(new ChangeTypeDelete('README'), self::ROLLBACK_CAPTURE_PATH, 1);

    // This capture is allowed to be destructive, should result in an mv of README
    $this->assertFalse(self::$fsAccessManager->isFile('README'));
    $this->assertEquals($contents, self::$fsAccessManager->fileGetContents(self::ROLLBACK_CAPTURE_PATH . DIRECTORY_SEPARATOR . 'payload/rollback/files/README'));
  }

  public function testSinglePatch() {
    $sut = self::sutFactory();
    $this->assertTrue(self::$fsAccessManager->isFile('test/file_depth1'));
    $contents = self::$fsAccessManager->fileGetContents('test/file_depth1');
    $sut->capture(new ChangeTypePatch('test/file_depth1'), self::ROLLBACK_CAPTURE_PATH, 1);

    // This capture must not be destructive; verify file remains.
    $this->assertEquals($contents, self::$fsAccessManager->fileGetContents('test/file_depth1'));
    $this->assertEquals($contents, self::$fsAccessManager->fileGetContents(self::ROLLBACK_CAPTURE_PATH . DIRECTORY_SEPARATOR . 'payload/rollback/files/test/file_depth1'));
  }

  public function testSingleWrite_newFile() {
    $sut = self::sutFactory();
    $sut->capture(new ChangeTypeWrite('some/sort/of/file.php'), self::ROLLBACK_CAPTURE_PATH, '2');

    $this->assertDeletion('some/sort/of/file.php', '2');
  }

  public function testSingleWrite_overwrite() {
    $sut = self::sutFactory();
    $contents = self::$fsAccessManager->fileGetContents('test/file_depth1');
    $this->assertNotEmpty($contents);
    $sut->capture(new ChangeTypeWrite('test/file_depth1'), self::ROLLBACK_CAPTURE_PATH, '2');
    // Unlike testSingleWrite_newFile, since this file exists the reversal is to capture the original.
    $this->assertEquals($contents, self::$fsAccessManager->fileGetContents(self::ROLLBACK_CAPTURE_PATH . DIRECTORY_SEPARATOR . 'payload/rollback/files/test/file_depth1'));
  }

  public function testSingleRename() {
    $sut = self::sutFactory();
    $contents = self::$fsAccessManager->fileGetContents('test/file_depth1');
    $sut->capture(new ChangeTypeRename('test/file_depth1', 'test/file_renamed'), self::ROLLBACK_CAPTURE_PATH, '1');

    $this->assertDeletion('test/file_renamed', '1');
    $this->assertEquals($contents, self::$fsAccessManager->fileGetContents(self::ROLLBACK_CAPTURE_PATH . DIRECTORY_SEPARATOR . 'payload/rollback/files/test/file_depth1'));
  }

  protected function assertDeletion($path, $runnerId) {
    $deletions = self::$fsAccessManager->fileGetContents(self::ROLLBACK_CAPTURE_PATH . DIRECTORY_SEPARATOR . "payload/rollback/deleted_files.$runnerId");
    $deletions = explode("\n", $deletions);
    $this->assertContains($path, $deletions);
  }
}