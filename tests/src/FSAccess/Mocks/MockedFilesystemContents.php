<?php


namespace Curator\Tests\FSAccess\Mocks;


class MockedFilesystemContents {
  public $directories = [
    'test',
    'test/a'
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

}
