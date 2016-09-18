<?php

use Curator\CuratorApplication;
use Curator\IntegrationConfig;
use \Curator\FSAccess\FSAccessManager;

class FSAccessTest extends PHPUnit_Framework_TestCase
{
  /**
   * @var CuratorApplication $app
   */
  protected $app;

  function setUp() {
    $this->app = new CuratorApplication(IntegrationConfig::getNullConfig());
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
   * @expectedException Curator\FSAccess\FileException
   * @expectedExceptionMessage FTP server reports 553 Could not create file
   */
  function testFtpFileException() {
    // TODO: Once multiple adapters exist, specify FTP
    /**
     * @var FSAccessManager $fs
     */
    $fs = $this->app['fs_access'];
    // In the docker_test_env, '/' is chrooted to the user's homedir,
    // and the chroot root isn't writable.
    $fs->setWorkingPath('/');

    $fs->filePutContents('test', 'this data from integration tests.');
  }

  function testFilePut() {
    $adapters_tested = 0;
    foreach ($this->getAdapterServices('write') as $writeAdapterService) {
      $this->app['fs_access.write_adapter'] = $this->app[$writeAdapterService];
      $name = $this->app['fs_access.write_adapter']->getAdapterName();

      /**
       * @var FSAccessManager $fs
       */
      $fs = $this->app['fs_access'];
      $fs->setWorkingPath('/home/ftptest/www');

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
}
