<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class PatchCopyBatchRunnableIterator extends AbstractRunnableIterator {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var ArchiveFileReader $archive_reader;
   */
  protected $archive_reader;

  protected $internal_iterator;

  /**
   * PatchCopyBatchRunnableIterator constructor.
   * @param \Curator\FSAccess\FSAccessManager $fs_access
   * @param \Curator\Cpkg\ArchiveFileReader $archive_reader
   * @param int $start_index
   */
  public function __construct(FSAccessManager $fs_access, ArchiveFileReader $archive_reader, $version, $start_index, $end_index) {
    $this->fs_access = $fs_access;
    $this->archive_reader = $archive_reader;
    $this->internal_iterator = new \AppendIterator($archive_reader->getRecursiveFileIterator("payload/$version/files"));
    $this->internal_iterator->append($archive_reader->getRecursiveFileIterator("payload/$version/patch_files"));
  }
}
