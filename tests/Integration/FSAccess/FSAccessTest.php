<?php

namespace Curator\Tests\Integration\FSAccess;

use Curator\AppManager;
use Curator\CuratorApplication;
use Curator\FSAccess\FileException;
use Curator\FSAccess\FileExistsException;
use Curator\IntegrationConfig;
use \Curator\FSAccess\FSAccessManager;

class FSAccessTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CuratorApplication $app
   */
  protected $app;

  function setUp() {
    // Re-initialize service container to provide default services.
    $app_manager = new AppManager(AppManager::RUNMODE_STANDALONE);
    $this->app = $app_manager->createApplication();
    $this->app['fs_access.ftp_config'] = $this->app->share(function() {
      return new TestFtpConfigurationProvider();
    });
  }

  /**
   * @param string $which
   *   'read' or 'write' adapters
   * @return string[]
   *   The full service names for all read or write adapters.
   */
  protected function getAdapterServices($which) {
    $prefix = "fs_access.${which}_adapter.";
    return array_filter(
      $this->app->keys(),
      function($service_name) use ($prefix) {
        return strncmp($service_name, $prefix, strlen($prefix)) === 0;
      }
    );
  }

  /**
   * @expectedException \Curator\FSAccess\FileException
   * @expectedExceptionMessage FTP server reports 553 Could not create file
   */
  function testFtpFileException() {
    $this->app['fs_access.write_adapter'] = $this->app->raw('fs_access.write_adapter.ftp');
    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->app['fs_access'];
    // In the docker_test_env, '/' isn't writable to the FTP user.
    $fs->setWorkingPath('/');
    $fs->setWriteWorkingPath('/');

    $fs->filePutContents('test', 'this data from integration tests.');
  }

  function testFileExists() {
    $this->app['fs_access.read_adapter'] = $this->app['fs_access.read_adapter.filesystem'];
    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->app['fs_access'];
    $fs->setWorkingPath('/');
    $fs->setWriteWorkingPath('/');

    $fs->isFile('/root/test');
  }

  function testFilePut() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      // FSAccessManager needs to be reinitialized for each write adapter
      $this->setUp();

      $this->app['fs_access.write_adapter'] = $this->app->raw($writeAdapterService);
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      /**
       * @var FSAccessManager $fs
       */
      $fs = $this->app['fs_access'];
      $fs->setWorkingPath('/home/ftptest/www');
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

  function testFilePut_chroot() {
    $this->app['fs_access.ftp_config'] = $this->app->share(function() {
      return new TestFtpConfigurationProvider('ftptest_chroot');
    });
    $this->app['fs_access.write_adapter'] = $this->app->raw('fs_access.write_adapter.ftp');

    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->app['fs_access'];
    $fs->setWorkingPath('/home/ftptest_chroot/www');
    $this->assertEquals(
      '/www',
      $fs->autodetectWriteWorkingPath()
    );
    $fs->setWriteWorkingPath('/www');

    $test_data = 'Data from integration test via chrooted ftp';
    $fs->filePutContents('test-ftp-chroot', $test_data);
    $this->assertEquals(
      $test_data,
      $fs->fileGetContents('test-ftp-chroot'),
      'Simple file can be written via chrooted ftp and read back.'
    );
  }

  function testExistingMkdirException() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      // FSAccessManager needs to be reinitialized for each write adapter
      $this->setUp();

      $this->app['fs_access.write_adapter'] = $this->app->raw($writeAdapterService);
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      /**
       * @var FSAccessManager $fs
       */
      $fs = $this->app['fs_access'];
      $fs->setWorkingPath('/home/ftptest');
      $fs->setWriteWorkingPath($fs->autodetectWriteWorkingPath());

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
      $this->setUp();

      $this->app['fs_access.write_adapter'] = $this->app->raw($writeAdapterService);
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      /**
       * @var FSAccessManager $fs
       */
      $fs = $this->app['fs_access'];
      $fs->setWorkingPath('/home/ftptest');
      $fs->setWriteWorkingPath($fs->autodetectWriteWorkingPath());

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
