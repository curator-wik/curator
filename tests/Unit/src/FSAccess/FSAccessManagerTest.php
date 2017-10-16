<?php

namespace Curator\Tests\Unit\FSAccess;

use Curator\FSAccess\FSAccessManager;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class FSAccessManagerTest extends \PHPUnit\Framework\TestCase {

  const PROJECT_PATH = '/within/a/project';

  /**
   * @var ReadAdapterMock $readAdapter_root
   *   A read adapter for a mock filesystem whose project root is '/'.
   */
  protected static $readAdapter_root;

  /**
   * @var WriteAdapterMock $writeAdapter_root
   *   A write adapter for a mock filesystem whose project root is '/'.
   */
  protected static $writeAdapter_root;

  /**
   * @var ReadAdapterMock $readAdapter_proj
   *   A read adapter for a mock filesystem whose project root is below '/'.
   */
  protected static $readAdapter_proj;

  /**
   * @var WriteAdapterMock $writeAdapter_proj
   *   A write adapter for a mock filesystem whose project root is below '/'.
   */
  protected static $writeAdapter_proj;

  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();

    self::$readAdapter_root = new ReadAdapterMock('/');
    self::$writeAdapter_root = new WriteAdapterMock('/');

    self::$readAdapter_proj = new ReadAdapterMock(self::PROJECT_PATH);
    self::$writeAdapter_proj = new WriteAdapterMock(self::PROJECT_PATH);
  }

  public function setUp() {
    parent::setUp();

    // Make fresh filesystem states before each test.
    $root_contents = new MockedFilesystemContents();
    $proj_contents = new MockedFilesystemContents();

    // Reset the write cwd
    self::$writeAdapter_root->setMockedCwd('/');
    self::$writeAdapter_proj->setMockedCwd(self::PROJECT_PATH);

    self::$readAdapter_proj->setFilesystemContents($proj_contents);
    self::$writeAdapter_proj->setFilesystemContents($proj_contents);

    self::$readAdapter_root->setFilesystemContents($root_contents);
    self::$writeAdapter_root->setFilesystemContents($root_contents);
  }

  /**
   * System under test factory.
   * @param bool|string $sys_root
   *   If TRUE, the test subject will be provided a mocked filesystem at the
   *   system root directory, for both read and write adapters.
   *
   *   If FALSE, the mocked filesystem contents will begin at self::PROJECT_PATH
   *   for both read and write adapters.
   *
   *   If "read", the mocked contents for the read adapter will begin at the
   *   system root directory, but the write adapter will see the equivalent
   *   filesystem contents at self::PROJECT_PATH, thus simulating a chroot()ed
   *   read adapter.
   *
   *   If "write", the mocked contents for the write adapter will begin at the
   *   root directory, but the read adapter will see the equivalent filesystem
   *   contents at self::PROJECT_PATH, thus simulating a chroot()ed write
   *   adapter.
   * @param bool $init_working_path
   *   If TRUE, the test subject will have setWorkingPath() called to align with
   *   the location where it is simulating mocked objects.
   * @param MockedFilesystemContents $filesystem_contents
   *   Optionally, you can provide customized filesystem contents.
   * @return FSAccessManager
   */
  protected static function sutFactory($sys_root = FALSE, $init_working_path = TRUE, MockedFilesystemContents $filesystem_contents = NULL) {
    if ($filesystem_contents != NULL) {
      // Set it on all the adapters; they get sanitized by setUp() on next test.
      self::$readAdapter_proj->setFilesystemContents($filesystem_contents);
      self::$writeAdapter_proj->setFilesystemContents($filesystem_contents);
      self::$readAdapter_root->setFilesystemContents($filesystem_contents);
      self::$writeAdapter_root->setFilesystemContents($filesystem_contents);
    }

    if ($sys_root === TRUE) {
      $s = new FSAccessManager(self::$readAdapter_root, self::$writeAdapter_root);
      if ($init_working_path) {
        $s->setWorkingPath('/');
        $s->setWriteWorkingPath('/');
      }
    } else {
      $read_adapter = self::$readAdapter_proj;
      $write_adapter = self::$writeAdapter_proj;
      $proj_fs = $read_adapter->getFilesystemContents();
      if ($sys_root === 'write') {
        $write_adapter = self::$writeAdapter_root;
        $write_adapter->setFilesystemContents($proj_fs);
      }
      if ($sys_root === 'read') {
        $read_adapter = self::$readAdapter_root;
        $read_adapter->setFilesystemContents($proj_fs);
      }

      $s = new FSAccessManager($read_adapter, $write_adapter);
      if ($init_working_path) {
        $s->setWorkingPath(self::PROJECT_PATH);
        $s->setWriteWorkingPath(self::PROJECT_PATH);
      }
    }
    return $s;
  }

  public static function assertMockFileExists(FSAccessManager $sut, $path) {
    self::assertTrue(
      $sut->isFile($path),
      sprintf('File "%s" does not exist.', $path)
    );
  }

  public static function assertMockDirExists(FSAccessManager $sut, $path) {
    self::assertTrue(
      $sut->isDir($path),
      sprintf('Directory "%s" does not exist.', $path)
    );
  }

  //<editor-fold desc="setWorkingPath">
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage $dir must be an absolute path.
   */
  public function testSetWorkingPathToRelativePath_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath('test');
  }

  public function testSetWorkingPathToAbsolutePath() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH);
  }

  public function testSetWorkingPathToAbsolutePathInsideProject() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Not a directory: /within/a/project/not/here
   */
  public function testSetWorkingPathToNonexistentPath_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/not/here');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Not a directory: /within/a/project/README
   */
  public function testSetWorkingPathToFile_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/README');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Not a directory: /README
   */
  public function testSetWorkingPathToFile_throws_2() {
    $sut = static::sutFactory(TRUE, FALSE);
    $sut->setWorkingPath('/README');
  }

  /**
   * @expectedException \InvalidArgumentException
   */
  public function testSetWorkingPathToEmpty_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath('');
  }

  /**
   * @expectedException \InvalidArgumentException
   */
  public function testSetWorkingPathToWrongType_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath([self::PROJECT_PATH]);
  }
  //</editor-fold>

  //<editor-fold desc="mkdir">
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "/within/a/project/test" is not within working path "/within/a/project/test/a".
   */
  public function testMkdir_outsideWorkingPath_throws() {
    $sut = static::sutFactory(FALSE, FALSE);

    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $sut->mkdir(self::PROJECT_PATH . '/test/b');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "up.link/new_dir" is not within working path "/within/a/project".
   */
  public function testMkdir_outsideWorkingPath_throws_2() {
    $sut = static::sutFactory();
    $sut->mkdir('up.link/new_dir');
  }

  protected static function _testMkdir_newRecursive(FSAccessManager $sut) {
    $sut->mkdir('whole/new/tree', TRUE);

    self::assertMockDirExists($sut, 'whole');
    self::assertMockDirExists($sut, 'whole/new');
    self::assertMockDirExists($sut, 'whole/new/tree');

    self::assertMockDirExists($sut, 'test/a');
    $sut->mkdir('test/a/new/dir/attached/to/existing/tree', TRUE);
    self::assertMockDirExists($sut, 'test/a');
    self::assertMockDirExists($sut, 'test/a/new/dir');
    self::assertMockDirExists($sut, 'test/a/new/dir/attached/to/existing/tree');
  }

  public function testMkdir_newRecursiveAtSysRoot() {
    static::_testMkdir_newRecursive(static::sutFactory(TRUE));
  }

  public function testMkdir_newRecursiveAtProjRoot() {
    static::_testMkdir_newRecursive(static::sutFactory(FALSE));
  }

  public function testMkdir_newAtSysRoot() {
    $sut = static::sutFactory(TRUE);
    $sut->mkdir('new', FALSE);
    self::assertMockDirExists($sut, 'new');

    $sut->mkdir('/new2', FALSE);
    self::assertMockDirExists($sut, 'new2');
  }

  public function testMkdir_newAtProjRoot() {
    $sut = static::sutFactory();
    $sut->mkdir('new', FALSE);
    self::assertMockDirExists($sut, 'new');

    $sut->mkdir(self::PROJECT_PATH . '/' . 'new2', FALSE);
    self::assertMockDirExists($sut, 'new2');
  }

  public function testMkdir_newAtCustomRoot() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(static::PROJECT_PATH . '/test');
    $sut->setWriteWorkingPath(static::PROJECT_PATH . '/test');
    $sut->mkdir(static::PROJECT_PATH . '/test/new', TRUE);
    self::assertMockDirExists($sut, 'new');
    self::assertMockDirExists($sut, static::PROJECT_PATH . '/test/new');
  }

  /**
   * @expectedException \Curator\FSAccess\FileExistsException
   */
  public function testMkdir_newProjRoot_throws() {
    $sut = static::sutFactory();
    $sut->mkdir(self::PROJECT_PATH);
  }

  /**
   * @expectedException \Curator\FSAccess\FileExistsException
   */
  public function testMkdir_newSysRoot_throws() {
    $sut = static::sutFactory(TRUE);
    $sut->mkdir('/');
  }

  public function testMkdir_newAtDepth1FromSysRoot() {
    $sut = static::sutFactory(TRUE);
    $sut->mkdir('/test/b');
    self::assertMockDirExists($sut, '/test/b');
  }

  public function testMkdir_newAtDepth2FromSysRoot() {
    $sut = static::sutFactory(TRUE);
    $sut->mkdir('/test/a/b');
    // Inconsistencies between absolute/relative paths in assertions of these
    // similar tests are intentional for enhanced coverage.
    self::assertMockDirExists($sut, 'test/a/b');
  }

  public function testMkdir_newAtDepth1FromProjRoot() {
    $sut = static::sutFactory(FALSE);
    $sut->mkdir(self::PROJECT_PATH . '/test/b');
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/b');
  }

  public function testMkdir_newAtDepth2FromProjRoot() {
    $sut = static::sutFactory(FALSE);
    $sut->mkdir(self::PROJECT_PATH . '/test/a/b');
    self::assertMockDirExists($sut, 'test/a/b');
  }

  public function testMkdir_newAtDepth2FromProjRootViaRelPath() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $sut->setWriteWorkingPath(self::PROJECT_PATH . '/test/a');
    $sut->mkdir('b');
    self::assertMockDirExists($sut, self::PROJECT_PATH  . '/test/a/b');
  }

  public function testMkdir_handlesSymlinks() {
    $sut = static::sutFactory(FALSE);
    $sut->mkdir('dir.link/b');
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/a/b');

    $sut->mkdir('dir.link/c/d', TRUE);
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/a/c');
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/a/c/d');
  }

  public function testMkdir_handlesPathIndirection() {
    $sut = static::sutFactory(FALSE);
    $sut->mkdir('test/a/nother/../b', TRUE);
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/a/b');
  }

  /**
   * @expectedException \Curator\FSAccess\FileNotFoundException
   */
  public function testMkdir_nonexistentTree_throws() {
    $sut = static::sutFactory();
    $sut->mkdir('random/place');
  }
  //</editor-fold>

  //<editor-fold desc="fileGetContents">
  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "/within/a/project/README" is not within working path "/within/a/project/test/a".
   */
  function testFileGetContents_outsideWorkingPath_throws() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $sut->fileGetContents(self::PROJECT_PATH . '/README');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "root.link/README" is not within working path "/within/a/project/test/a".
   */
  function testFileGetContents_outsideWorkingPath_throws_2() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $sut->fileGetContents('root.link/README');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "/test/file_depth1" is not within working path "/test/a".
   */
  function testFileGetContents_outsideWorkingPath_throws_3() {
    $sut = static::sutFactory(TRUE, FALSE);
    $sut->setWorkingPath('/test/a');
    $sut->fileGetContents('/test/file_depth1');
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "/within/a/projectfoo/test/file_depth1" is not within working path "/within/a/project".
   */
  function testFileGetContents_outsideWorkingPath_throws_4() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH);
    $sut->fileGetContents(self::PROJECT_PATH . 'foo/test/file_depth1');
  }

  function testFileGetContents() {
    $sut = static::sutFactory();
    self::assertEquals(
      self::$readAdapter_proj->getFilesystemContents()->files['README'],
      $sut->fileGetContents('README')
    );

    self::assertEquals(
      self::$readAdapter_proj->getFilesystemContents()->files['test/file_depth1'],
      $sut->fileGetContents('test/file_depth1')
    );
  }

  function testFileGetContents_handlesPathIndirection() {
    $sut = static::sutFactory();
    self::assertEquals(
      self::$readAdapter_proj->getFilesystemContents()->files['README'],
      $sut->fileGetContents('test/../README')
    );
  }

  /**
   * @expectedException \Curator\FSAccess\FileExistsException
   */
  function testFileGetContents_dir_throws() {
    $sut = static::sutFactory();
    $sut->fileGetContents('test');
  }

  /**
   * @expectedException \Curator\FSAccess\FileExistsException
   */
  function testFileGetContents_dir_throws_2() {
    $sut = static::sutFactory();
    $sut->fileGetContents('socket_sim');
  }

  /**
   * @expectedException \Curator\FSAccess\FileNotFoundException
   */
  function testFileGetContents_nonexistent_throws() {
    $sut = static::sutFactory();
    $sut->fileGetContents('nothing/here');
  }

  /**
   * @expectedException \Curator\FSAccess\FileNotFoundException
   */
  function testFileGetContents_nonexistent_throws_2() {
    $sut = static::sutFactory();
    $sut->fileGetContents('broken.link');
  }

  function testFileGetContents_followsSymlinks() {
    $sut = static::sutFactory();
    self::assertEquals(
      self::$readAdapter_proj->getFilesystemContents()->files['README'],
      $sut->fileGetContents('README.link')
    );

    self::assertEquals(
      self::$readAdapter_proj->getFilesystemContents()->files['README'],
      $sut->fileGetContents('test/a/up.link/a/root.link/README')
    );
  }
  //</editor-fold>

  //<editor-fold desc="mv">
  public function testMv() {
    $sut = static::sutFactory();
    $contents = $sut->fileGetContents('README');
    $sut->mv('README', 'README.txt');
    $this->assertEquals($contents, $sut->fileGetContents('README.txt'));

    $sut->mv('README.txt', 'README');
    $this->assertEquals($contents, $sut->fileGetContents('README'));

    $sut->mv('README.link', 'test/README.link');
    $this->assertEquals($contents, $sut->fileGetContents('test/README.link'));
    $this->assertEquals($contents, $sut->fileGetContents('README'));
    $this->assertFalse($sut->isFile('README.link'));
  }
  //</editor-fold>

  //<editor-fold desc="rm, unlink, rmDir">
  public function testRm() {
    $sut = static::sutFactory();
    $this->assertTrue($sut->isFile('README.link'));
    $sut->rm('README.link');
    $this->assertFalse($sut->isFile('README.link'));

    $this->assertTrue($sut->isFile('README'));
    $sut->rm('README');
    $this->assertFalse($sut->isFile('README'));

    $this->assertTrue($sut->isDir('test/empty_dir'));
    $sut->rmDir('test/empty_dir');
    $this->assertFalse($sut->isDir('test/empty_dir'));
  }
  //</editor-fold>

  //<editor-fold desc="autodetectWriteWorkingPath">
  // Some basic checks without chroots or heterogeneous path parsers:
  public function testAutodetectWriteWorkingPath() {
    $sut = static::sutFactory(TRUE, TRUE);
    $this->assertEquals('/', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_2() {
    $sut = static::sutFactory(TRUE, FALSE);
    $sut->setWorkingPath('/test');
    $this->assertEquals('/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_3() {
    $sut = static::sutFactory(TRUE, FALSE);
    $sut->setWorkingPath('/test/a');
    $this->assertEquals('/test/a', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_4() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $this->assertEquals(self::PROJECT_PATH . '/test/a', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_5() {
    $sut = static::sutFactory(FALSE, TRUE);
    $this->assertEquals(self::PROJECT_PATH, $sut->autodetectWriteWorkingPath());
  }

  // Chroot()ed write adapter
  public function testAutodetectWriteWorkingPath_6() {
    $sut = static::sutFactory('write', TRUE);
    $this->assertEquals('/', $sut->autodetectWriteWorkingPath());
  }

  // Chroot()ed write adapter, subdirectory
  public function testAutodetectWriteWorkingPath_7() {
    $sut = static::sutFactory('write', FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test');
    $this->assertEquals('/test', $sut->autodetectWriteWorkingPath());
  }

  // Chroot()ed write adapter, subdirectory
  public function testAutodetectWriteWorkingPath_8() {
    $sut = static::sutFactory('write', FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/a');
    $this->assertEquals('/test/a', $sut->autodetectWriteWorkingPath());
  }

  // Contrived examples where differentiating path components is hard.
  protected function createContrivedFSContents() {
    $contrived_fs = new MockedFilesystemContents();
    $contrived_fs->clearAll();
    $contrived_fs->directories = array(
      'test',
      'test/test',
      'test/test/test',
      'test/test/test/test'
    );
    $contrived_fs->files = array(
      'test/test/test/test/file' => 'stuff'
    );
    return $contrived_fs;
  }

  public function testAutodetectWriteWorkingPath_9() {
    $sut = static::sutFactory('write', FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath(self::PROJECT_PATH);
    $this->assertEquals('/', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_10() {
    $sut = static::sutFactory('write', FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath(self::PROJECT_PATH . '/test');
    $this->assertEquals('/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_11() {
    $sut = static::sutFactory('write', FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/test');
    $this->assertEquals('/test/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_12() {
    $sut = static::sutFactory('write', FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/test/test/test');
    $this->assertEquals('/test/test/test/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_13() {
    $sut = static::sutFactory(FALSE, TRUE, $this->createContrivedFSContents());
    $this->assertEquals('/within/a/project', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_14() {
    $sut = static::sutFactory(TRUE, FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath('/test/test');
    $this->assertEquals('/test/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_15() {
    $sut = static::sutFactory(FALSE, FALSE, $this->createContrivedFSContents());
    $sut->setWorkingPath(self::PROJECT_PATH . '/test/test');
    $this->assertEquals(self::PROJECT_PATH . '/test/test', $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_WithNoCwd() {
    $sut = static::sutFactory(FALSE, TRUE);
    static::$writeAdapter_proj->setMockedCwd('');
    $this->assertEquals(self::PROJECT_PATH, $sut->autodetectWriteWorkingPath());
  }

  public function testAutodetectWriteWorkingPath_WithNoCwd_chrooted() {
    $sut = static::sutFactory('write', TRUE);
    static::$writeAdapter_proj->setMockedCwd('');
    $this->assertEquals('/', $sut->autodetectWriteWorkingPath());
  }

  /**
   * @expectedException \Curator\FSAccess\FileException
   * @expectedExceptionMessage Auto-detection could not locate the path for writing.
   */
  public function testAutodetectWriteWorkingPath_AbsentWorkingPath_throws() {
    $contents = new MockedFilesystemContents();
    $sut = static::sutFactory(TRUE, FALSE, $contents);
    $sut->setWorkingPath('/test/a');
    $contents->directories = ['test'];
    $sut->autodetectWriteWorkingPath();
  }
  //</editor-fold>
}
