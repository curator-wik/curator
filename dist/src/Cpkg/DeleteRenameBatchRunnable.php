<?php


namespace Curator\Cpkg;


use Curator\Batch\DescribedRunnableInterface;
use Curator\FSAccess\FSAccessManager;

class DeleteRenameBatchRunnable implements DescribedRunnableInterface {
  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  /**
   * @var string $operation
   *   'r'ename or 'd'elete
   */
  protected $operation;

  /**
   * @var string $source
   *   FSAccessManager-recognized path to the file to operate on.
   */
  protected $source;

  /**
   * @var string|null $destination
   *   If a rename, the FSAccessManager-recognized new name of the file.
   */
  protected $destination;

  /**
   * DeleteRenameBatchRunnable constructor.
   * @param \Curator\FSAccess\FSAccessManager $fs_access
   * @param string $operation
   *   'd'elete or 'r'ename.
   * @param string $source
   *   FSAccessManager-recognized path to the file to operate on.
   * @param string|null $destination
   *   If a rename, the FSAccessManager-recognized new name of the file.
   */
  public function __construct(FSAccessManager $fs_access, $operation, $source, $destination = NULL) {

  }
}
