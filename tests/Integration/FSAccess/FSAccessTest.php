<?php

namespace Curator\Tests\Integration\FSAccess;

use Curator\AppManager;
use Curator\CuratorApplication;
use Curator\FSAccess\FileExistsException;
use \Curator\FSAccess\FSAccessManager;
use Curator\IntegrationConfig;
use Curator\Tests\Functional\FunctionalTestAppManager;

class FSAccessTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CuratorApplication $app
   */
  protected $app;

  /**
   * @param array $io_adapters
   * @return FSAccessManager
   */
  protected function sutFactory($site_root, $io_adapters = ['filesystem', 'filesystem'], $ftp_user = NULL) {
    // Re-initialize service container to provide desired services.
    $serviceOverrides = [
      'fs_access.read_adapter' => 'fs_access.read_adapter.' . $io_adapters[0],
      'fs_access.write_adapter' => 'fs_access.write_adapter.' . $io_adapters[1],
      'fs_access.ftp_config' => [
        function() use($ftp_user) {
          return new TestFtpConfigurationProvider($ftp_user);
        },
        TRUE
      ],
    ];

    $app_manager = new FunctionalTestAppManager(AppManager::RUNMODE_STANDALONE, $serviceOverrides);
    $this->app = $app_manager->applyIntegrationConfig((new IntegrationConfig())->setSiteRootPath($site_root));

    return $this->app['fs_access'];
  }

  /**
   * @param string $which
   *   'read' or 'write' adapters
   * @return string[]
   *   The service names for all read or write adapters.
   */
  protected function getAdapterServices($which) {
    static $app = null;
    if ($app === null) {
      // most any instance will do, we just want to examine the services
      $this->sutFactory('/');
      $app = $this->app;
    }
    $prefix = "fs_access.${which}_adapter.";

    $full_service_ids = array_filter(
      $app->keys(),
      function($service_name) use ($prefix) {
        return strncmp($service_name, $prefix, strlen($prefix)) === 0;
      }
    );

    return array_map(function($item) use ($prefix) {
      return substr($item, strlen($prefix));
    }, $full_service_ids);
  }

  /**
   * @expectedException \Curator\FSAccess\FileException
   * @expectedExceptionMessage FTP server reports 553 Could not create file
   */
  function testFtpFileException() {
    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->sutFactory('/', ['filesystem', 'ftp']);
    $fs->filePutContents('test', 'this data from integration tests.');
  }

  function testFileExists() {
    $fs = $this->sutFactory('/');
    $fs->isFile('/root/test');
  }

  function testFilePut_AllRegisteredWriteAdapters() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      // FSAccessManager needs to be reinitialized for each write adapter
      $fs = $this->sutFactory('/home/ftptest/www', ['filesystem', $writeAdapterService]);
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      $fs->setWriteWorkingPath($fs->autodetectWriteWorkingPath());

      $test_data = "Data from integration test via $name";
      $fs->filePutContents("test-$name", $test_data);
      $this->assertEquals(
        $test_data,
        $fs->fileGetContents("test-$name"),
        "Simple file can be written via $name and read back."
      );
      $adapters_tested++;
    }

    $this->assertGreaterThan(0, $adapters_tested);
  }

  function testAutodetectWriteWorkingPath_chroot() {
    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->sutFactory('/home/ftptest_chroot/www', ['filesystem', 'ftp'], 'ftptest_chroot');

    $this->assertEquals(
      '/www',
      $fs->autodetectWriteWorkingPath()
    );
  }

  protected function _testFilePut_chroot() {
    $fs = $this->app['fs_access'];
    $test_data = 'Data from integration test via chrooted ftp';
    $fs->filePutContents('test-ftp-chroot', $test_data);
    return $test_data;
  }

  function testFilePut_chroot() {
    $fs = $this->sutFactory('/home/ftptest_chroot/www', ['filesystem', 'ftp'], 'ftptest_chroot');
    $fs->setWriteWorkingPath('/www');
    $expected = $this->_testFilePut_chroot();
    $this->assertEquals(
      $expected,
      $fs->fileGetContents('test-ftp-chroot'),
      'Simple file can be written via chrooted ftp and read back.'
    );
  }

  function testMv_chroot() {
    $fs = $this->sutFactory('/home/ftptest_chroot/www', ['filesystem', 'ftp'], 'ftptest_chroot');
    $fs->setWriteWorkingPath('/www');
    $expected = $this->_testFilePut_chroot();

    $fs->mv('test-ftp-chroot', 'test-ftp-chroot-moved');
    $this->assertEquals(
      $expected,
      $fs->fileGetContents('test-ftp-chroot-moved')
    );
  }

  function testUnlink_chroot() {
    $fs = $this->sutFactory('/home/ftptest_chroot/www', ['filesystem', 'ftp'], 'ftptest_chroot');
    $fs->setWriteWorkingPath('/www');
    $this->_testFilePut_chroot();

    $this->assertTrue($fs->isFile('test-ftp-chroot'));
    $fs->unlink('test-ftp-chroot');
    $this->assertFalse($fs->isFile('test-ftp-chroot'));
  }

  function testMkdirRmdir_chroot() {
    $fs = $this->sutFactory('/home/ftptest_chroot/www', ['filesystem', 'ftp'], 'ftptest_chroot');
    $fs->setWriteWorkingPath('/www');

    $this->assertFalse($fs->isDir('test-mkdir'));
    $fs->mkdir('test-mkdir');
    $this->assertTrue($fs->isDir('test-mkdir'));
    $fs->rmDir('test-mkdir');
    $this->assertFalse($fs->isDir('test-mkdir'));
  }

  function testExistingMkdirException() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      // FSAccessManager needs to be reinitialized for each write adapter
      $fs = $this->sutFactory('/home/ftptest', ['filesystem', $writeAdapterService]);
      $fs->setWriteWorkingPath($fs->autodetectWriteWorkingPath());
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      try {
        $fs->mkdir('www');
        $this->assertFalse(TRUE, "No exception thrown when making an existing directory with $name.");
      } catch (FileExistsException $e) {
        $this->assertEquals(
          0,
          $e->getCode(),
          "FileExistsException thrown by $name should have code 1"
        );
      }
      $adapters_tested++;
    }

    $this->assertGreaterThan(0, $adapters_tested);
  }

  function testMkdirOverExistingFileException() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      // FSAccessManager needs to be reinitialized for each write adapter
      $fs = $this->sutFactory('/home/ftptest', ['filesystem', $writeAdapterService]);
      $fs->setWriteWorkingPath($fs->autodetectWriteWorkingPath());
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      try {
        $fs->mkdir('.profile');
        $this->assertFalse(
          TRUE,
          "No exception thrown when attempting to make directory over existing file with $name."
        );
      } catch (FileExistsException $e) {
        $this->assertEquals(
          1,
          $e->getCode(),
          "FileExistsException thrown by $name should have code 1"
        );
      }
      $adapters_tested++;
    }

    $this->assertGreaterThan(0, $adapters_tested);
  }
}
