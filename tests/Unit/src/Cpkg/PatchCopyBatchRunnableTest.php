<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\ArchiveFileReader;
use Curator\Cpkg\PatchCopyBatchRunnable;
use Curator\FSAccess\FSAccessManager;
use Curator\Tests\Unit\FSAccess\Mocks\MockedFilesystemContents;
use Curator\Tests\Unit\FSAccess\Mocks\ReadAdapterMock;
use Curator\Tests\Unit\FSAccess\Mocks\WriteAdapterMock;

class PatchCopyBatchRunnableTest extends \PHPUnit\Framework\TestCase {

  /**
   * @param $cpkg_path
   * @param $operation
   * @param $source_in_cpkg
   * @param $destination
   * @param array $options
   * @return PatchCopyBatchRunnable
   */
  protected function sutFactory($cpkg_path, $operation, $source_in_cpkg, $destination, $options = []) {
    $fs_access = isset($options['fs_access']) ?
      $options['fs_access'] :
      new FSAccessManager(new ReadAdapterMock('/'), new WriteAdapterMock('/'));
    $fs_access->setWorkingPath('/');

    $sut = new PatchCopyBatchRunnable(
      $fs_access,
      new ArchiveFileReader($cpkg_path),
      isset($options['id']) ? $options['id'] : 1,
      $operation,
      $source_in_cpkg,
      $destination
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

  public function testOptimisticMkdir() {
    // Build an FSAccessManager mock that reports directory "dir" is absent,
    // but fails with already exists when creating it. This simulates a race
    // condition with two runnables creating the directory.
    $read_contents = new MockedFilesystemContents();
    $read_contents->clearAll();
    $write_contents = new MockedFilesystemContents();
    $write_contents->clearAll();
    $write_contents->directories = ['dir', 'parent', 'parent/child'];

    $read_adapter = new ReadAdapterMock('/');
    $read_adapter->setFilesystemContents($read_contents);
    $write_adapter = new WriteAdapterMock('/');
    $write_adapter->setFilesystemContents($write_contents);
    $fs_access = new FSAccessManager($read_adapter, $write_adapter);

    $sut = $this->sutFactory(
      $this->p('multiple-files-patches.zip'),
      'copy',
      'payload/1.2.4/files/dir',
      'dir',
      ['fs_access' => $fs_access]
    );

    $task_mock = $this->getMock('\mbaynton\BatchFramework\TaskInterface');
    $instance_mock = $this->getMock('\mbaynton\BatchFramework\TaskInstanceStateInterface');
    $sut->run($task_mock, $instance_mock);

    $sut = $this->sutFactory(
      $this->p('multiple-files-patches.zip'),
      'copy',
      'payload/1.2.4/files/dir',
      'parent/child',
      ['fs_access' => $fs_access]
    );
    $task_mock = $this->getMock('\mbaynton\BatchFramework\TaskInterface');
    $instance_mock = $this->getMock('\mbaynton\BatchFramework\TaskInstanceStateInterface');
    $sut->run($task_mock, $instance_mock);
  }
}
