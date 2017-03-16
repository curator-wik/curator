<?php


namespace Curator\Tests\Functional\Cpkg;


use Curator\Tests\Functional\WebTestCase;

class PatchCopyBatchTest extends CpkgWebTestCase {
  public function testMultiplePatchesAndCopies() {
    $initial_dirs = ['subdir'];
    $initial_files = [
      'patch_target_1' => 'The quick brown fox jumps over the lazy dog\'s back.
This is the first file that needs to be patched.
Little boxes on the hillside.',
      'subdir/patch_target_2' => 'The quick brown fox jumps over the lazy dog\'s back.
This is the second file that needs to be patched.
Little boxes on the hillside.'
    ];

    $expected_dirs = ['subdir', 'empty_subdir'];
    $expected_files = [
      'copied_file_1' => 'this is a new file',
      'subdir/copied_file_2' => 'this is another new file',
      'patch_target_1' => 'The quick brown fox jumps over the lazy dog\'s back.
This is the first file that has been patched.
Little boxes on the hillside.',
      'subdir/patch_target_2' => 'The quick brown fox jumps over the lazy dog\'s back.
This is the second file that has been patched.
Little boxes on the hillside.'
    ];

    $this->_testCpkgBatchApplication('multiple-files-patches.zip', $initial_dirs, $expected_dirs, $initial_files, $expected_files, 1);
  }
}
