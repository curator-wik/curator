<?php
namespace Curator\Tests\Unit\Persistence;

use Curator\FSAccess\FSAccessManager;
use Curator\IntegrationConfig;
use Curator\Persistence\FilePersistence;
use Curator\Tests\Shared\Traits\Persistence\PersistenceTestsTrait;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Shared\Mocks\ReaderWriterLockMock;

class FilePersistenceTest extends \PHPUnit_Framework_TestCase {

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

  protected function sutFactory($sys_root = FALSE) {
    if ($sys_root === TRUE) {
      $fs = new FSAccessManager(self::$readAdapter_root, self::$writeAdapter_root);
      $ra = self::$readAdapter_root;
      $dir = '/';
    } else {
      $fs = new FSAccessManager(self::$readAdapter_proj, self::$writeAdapter_proj);
      $ra = self::$readAdapter_proj;
      $dir = self::PROJECT_PATH;
    }

    $fs->setWorkingPath($dir);
    $fs->setWriteWorkingPath($dir);

    $lock_stub = new ReaderWriterLockMock();

    $s = new FilePersistence($fs, $ra, $lock_stub, $dir, 'php');
    return $s;
  }

  use PersistenceTestsTrait;
}
