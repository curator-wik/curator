<?php


namespace Curator\Tests\Unit\FSAccess\Mocks;


class MockedFilesystemContents {
  public $directories = [
    'test',
    'test/a',
    'test/empty_dir'
  ];

  public $files = [
    'README' => 'This is the README file contents',
    'test/file_depth1' => 'A file at test/file_depth1',
  ];

  public $symlinks = [
    'up.link' => '../',
    'README.link' => 'README',
    'shortcut.link' => 'test/file_depth1',
    'dir.link' => 'test/a',
    'test/a/up.link' => '../',
    'test/a/root.link' => '../../',
    'broken.link' => 'nothing/here',
  ];

  public $specials = [
    'socket_sim',
  ];

  /**
   * Makes the mocked filesystem completely empty.
   */
  public function clearAll() {
    $this->directories = array();
    $this->files = array();
    $this->symlinks = array();
    $this->specials = array();
  }
}
