<?php


namespace Curator\Cpkg;


use Curator\FSAccess\FSAccessManager;
use Curator\Rollback\RollbackCaptureInterface;
use mbaynton\BatchFramework\AbstractRunnableIterator;

class PatchCopyBatchRunnableIterator extends AbstractRunnableIterator {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var CpkgReaderPrimitivesInterface $archive_reader ;
   */
  protected $archive_reader;

  /**
   * @var RollbackCaptureInterface $rollback
   */
  protected $rollback;

  /**
   * @var \AppendIterator $internal_iterator
   */
  protected $internal_iterator;

  /**
   * @var int $start_index
   */
  protected $start_index;

  /**
   * @var int $current_index;
   */
  protected $current_index;

  /**
   * @var int $end_index;
   */
  protected $end_index;

  /**
   * @var string $destination_excess_prefix
   */
  protected $destination_excess_prefix;

  /**
   * @var string $source_excess_prefix
   *   The portion of iterator paths that represents the phar archive itself,
   *   and the portion that must be stripped off to provide the location within
   *   the cpkg.
   */
  protected $source_excess_prefix;

  /**
   * PatchCopyBatchRunnableIterator constructor.
   *
   * @param \Curator\FSAccess\FSAccessManager $fs_access
   * @param CpkgReaderPrimitivesInterface $archive_reader
   * @param int $start_index
   */
  public function __construct(FSAccessManager $fs_access, CpkgReaderPrimitivesInterface $archive_reader, RollbackCaptureInterface $rollback, $version, $start_index, $end_index) {
    $this->fs_access = $fs_access;
    $this->archive_reader = $archive_reader;
    $this->rollback = $rollback;

    $this->internal_iterator = self::buildPatchCopyInternalIterator($archive_reader, $version);

    if ($this->internal_iterator->valid()) {
      $this->detectIteratorPathPrefix();
    }

    $this->start_index = $start_index;
    $this->end_index = $end_index;
    $this->rewind();
  }

  /**
   * Gets an iterator over all files and patches for a particular version
   * in the cpkg archive being read by $archive_reader.
   *
   * @param CpkgReaderPrimitivesInterface $archive_reader
   * @param $version
   *
   * @return \AppendIterator
   */
  public static function buildPatchCopyInternalIterator(CpkgReaderPrimitivesInterface $archive_reader, $version) {
    $internal_iterator = new \AppendIterator();
    try {
      $internal_iterator->append($archive_reader->getRecursiveFileIterator("payload/$version/files"));
    } catch (\UnexpectedValueException $e) {
      // When no files directory is present.
    }

    try {
      $internal_iterator->append(
        new PatchFilterIterator(
          $archive_reader->getRecursiveFileIterator("payload/$version/patch_files")
        )
      );
    } catch (\UnexpectedValueException $e) {
      // When no patch_files directory is present.
    }

    return $internal_iterator;
  }

  /**
   * Precondition: Internal iterator is at first position.
   */
  protected function detectIteratorPathPrefix() {
    $path = $this->internal_iterator->current()->getPathname();
    if (!empty($path)) {
      // Example: 'phar:///path/to/cpkg.zip/payload/1.2.3'
      $this->destination_excess_prefix = dirname(dirname($path));
      $this->source_excess_prefix = dirname(dirname($this->destination_excess_prefix));
    }
  }

  protected function getSourceInCpkg($full_path) {
    return substr($full_path, strlen($this->source_excess_prefix));
  }

  protected function getDestination($full_path, $operation) {
    $destination = substr($full_path, strlen($this->destination_excess_prefix));
    if ($operation == 'patch') {
      if (strncmp($destination, '/patch_files', 12) !== 0) {
        throw new \LogicException(sprintf('Requested %s destination from non-%s cpkg path "%s"', $operation, $operation, $destination));
      }
      return substr($destination, 13);
    } else {
      if (strncmp($destination, '/files', 6) !== 0) {
        throw new \LogicException(sprintf('Requested %s destination from non-%s cpkg path "%s"', $operation, $operation, $destination));
      }
      return substr($destination, 7);
    }
  }

  public function rewind() {
    $this->internal_iterator->rewind();
    for ($this->current_index = 0; $this->current_index < $this->start_index; $this->current_index++) {
      $this->internal_iterator->next();
    }
  }

  /**
   * @return PatchCopyBatchRunnable
   */
  public function current() {
    if ($this->valid()) {
      /**
       * @var \SplFileInfo $current
       */
      $current = $this->internal_iterator->current();
      if ($this->internal_iterator->getInnerIterator() instanceof PatchFilterIterator) {
        $operation = 'patch';
        $destination = substr($this->getDestination($current->getPathname(), $operation), 0, -1 * strlen('.patch'));
      } else {
        $operation = 'copy';
        $destination = $this->getDestination($current->getPathname(), $operation);
      }

      return new PatchCopyBatchRunnable(
        $this->fs_access,
        $this->archive_reader,
        $this->rollback,
        $this->current_index,
        $operation,
        $this->getSourceInCpkg($current->getPathname()),
        $destination
      );
    } else {
      return NULL;
    }
  }

  public function next() {
    if ($this->valid()) {
      $this->internal_iterator->next();
      $this->current_index++;
    }
  }

  public function valid() {
    return ($this->current_index <= $this->end_index || $this->end_index === -1)
      && $this->internal_iterator->valid();
  }
}
