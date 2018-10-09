<?php


namespace Curator\Tests\Functional\Cpkg;


class DeleteRenameBatchTest extends CpkgWebTestCase {

  /**
   * - Perform a job with both deletes and renames, ensure all of both types
   *   are processed.
   * - Cause there to be enough runnables to require > 1 runner incarnation.
   * - Verify all changes were made to filesystem at end.
   */
  // Tests two deletions and many renames using 4 runners.
  public function testMultipleDeletesAndRenames1() {
    $initial_dirs = ['renames', 'deleteme-dir'];
    $initial_files = [
      'deleteme-file' => 'hello world',
      'deleteme-dir/file' => 'hello world',
      'changelog.1.2.4' => 'More better.',
    ];
    for($i = 1; $i <= 30; $i++) {
      $initial_files["renames/fileA$i"] = $i;
    }

    $expected_dirs = ['renames', '/app'];
    $expected_files = [];
    for ($i = 1; $i <= 30; $i++) {
      $expected_files["renames/fileB$i"] = $i;
    }
    $expected_files['changelog.1.2.5'] = 'More better.';

    $this->_testCpkgBatchApplication('multiple-deletes-renames.zip', $initial_dirs, $expected_dirs, $initial_files, $expected_files);
  }

  // Tests exactly as many deletions as there are runners.
  public function testMultipleDeletesAndRenames2() {
    $initial_dirs = ['renames', 'deleteme-dir'];
    $initial_files = [
      'deleteme-file' => 'hello world',
      'deleteme-file2' => 'hello world',
      'deleteme-file3' => 'x',
      'deleteme-dir/file' => 'hello world',
      'changelog.1.2.4' => 'More better.',
    ];
    for($i = 1; $i <= 30; $i++) {
      $initial_files["renames/fileA$i"] = $i;
    }

    $expected_dirs = ['renames', '/app'];
    $expected_files = [];
    for ($i = 1; $i <= 30; $i++) {
      $expected_files["renames/fileB$i"] = $i;
    }
    $expected_files['changelog.1.2.5'] = 'More better.';

    $this->_testCpkgBatchApplication('multiple-deletes-renames2.zip', $initial_dirs, $expected_dirs, $initial_files, $expected_files);
  }

  // Tests more deletions as there are runners.
  public function testMultipleDeletesAndRenames3() {
    $initial_dirs = ['renames', 'deleteme-dir'];
    $initial_files = [
      'deleteme-file' => 'hello world',
      'deleteme-file2' => 'hello world',
      'deleteme-file3' => 'x',
      'deleteme-file4' => 'y',
      'deleteme-file5' => 'z',
      'deleteme-dir/file' => 'hello world',
      'changelog.1.2.4' => 'More better.',
    ];
    for($i = 1; $i <= 30; $i++) {
      $initial_files["renames/fileA$i"] = $i;
    }

    $expected_dirs = ['renames', '/app'];
    $expected_files = [];
    for ($i = 1; $i <= 30; $i++) {
      $expected_files["renames/fileB$i"] = $i;
    }
    $expected_files['changelog.1.2.5'] = 'More better.';

    $this->_testCpkgBatchApplication('multiple-deletes-renames3.zip', $initial_dirs, $expected_dirs, $initial_files, $expected_files);
  }

  // Tests zero deletions.
  public function testMultipleRenames() {
    $initial_dirs = ['renames'];
    $initial_files = [
      'changelog.1.2.4' => 'More better.',
    ];
    for($i = 1; $i <= 35; $i++) {
      $initial_files["renames/fileA$i"] = $i;
    }

    $expected_dirs = ['renames', '/app'];
    $expected_files = [];
    for ($i = 1; $i <= 35; $i++) {
      $expected_files["renames/fileB$i"] = $i;
    }
    $expected_files['changelog.1.2.5'] = 'More better.';

    $this->_testCpkgBatchApplication('multiple-renames.zip', $initial_dirs, $expected_dirs, $initial_files, $expected_files);
  }

}
