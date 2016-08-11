<?php

namespace Curator\Tests\FSAccess;

use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FSAccessManager;
use Curator\Tests\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\FSAccess\Mocks\WriteAdapterMock;

class FSAccessManagerTest extends \PHPUnit_Framework_TestCase {

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

    self::$readAdapter_proj = new ReadAdapterMock(self::PROJECT_PATH, NULL);
    self::$writeAdapter_proj = new WriteAdapterMock(self::PROJECT_PATH, NULL);
  }

  public function setUp() {
    parent::setUp();

    // Make fresh filesystem states before each test.
    $root_contents = new MockedFilesystemContents();
    $proj_contents = new MockedFilesystemContents();

    self::$readAdapter_proj->setFilesystemContents($proj_contents);
    self::$writeAdapter_proj->setFilesystemContents($proj_contents);

    self::$readAdapter_root->setFilesystemContents($root_contents);
    self::$writeAdapter_root->setFilesystemContents($root_contents);
  }

  /**
   * System under test factory.
   * @param bool $sys_root
   *   If TRUE, the test subject will be simulate its mocked filesystem at the
   *   system root directory. Otherwise, the mocked files and structures are
   *   simulated to exist at self::PROJECT_PATH.
   * @param bool $init_working_path
   *   If TRUE, the test subject will have setWorkingPath() called to align with
   *   the location where it is simulating mocked objects.
   * @return FSAccessManager
   */
  protected static function sutFactory($sys_root = FALSE, $init_working_path = TRUE) {
    if ($sys_root) {
      $s = new FSAccessManager(self::$readAdapter_root, self::$writeAdapter_root);
      if ($init_working_path) {
        $s->setWorkingPath('/');
      }
    } else {
      $s = new FSAccessManager(self::$readAdapter_proj, self::$writeAdapter_proj);
      if ($init_working_path) {
        $s->setWorkingPath(self::PROJECT_PATH);
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

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage Path "/within/a/project/test" is not within working path "/within/a/project/test/a".
   */
  public function testMkdir_outsideWorkingPath_throws() {
    $sut = static::sutFactory();

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
    $sut->mkdir('b');
    self::assertMockDirExists($sut, self::PROJECT_PATH  . '/test/a/b');
  }

  public function testMkdir_handlesBackslashes() {
    $sut = static::sutFactory(FALSE, FALSE);
    $sut->setWorkingPath(self::PROJECT_PATH . '\\test');
    $sut->mkdir('a\\b');
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/a/b');

    $sut->mkdir(self::PROJECT_PATH . '\\test\\c');
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/c');

    $sut->mkdir(self::PROJECT_PATH . '\\test\\d\\e', TRUE);
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/d/e');

    $sut->mkdir('f\\g', TRUE);
    self::assertMockDirExists($sut, self::PROJECT_PATH . '/test/f/g');
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

  // TODO: Test traversals of all those interesting symlinks

}
