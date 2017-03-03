<?php


namespace Curator\Cpkg;


use Curator\Batch\DescribedRunnableInterface;
use Curator\FSAccess\FSAccessManager;
use mbaynton\BatchFramework\AbstractRunnable;
use mbaynton\BatchFramework\TaskInstanceStateInterface;
use mbaynton\BatchFramework\TaskInterface;

class DeleteRenameBatchRunnable extends AbstractRunnable implements DescribedRunnableInterface {
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
   * @param int $id
   *   The runnable id.
   * @param string $operation
   *   'delete' or 'rename'.
   * @param string $source
   *   FSAccessManager-recognized path to the file to operate on.
   * @param string|null $destination
   *   If a rename, the FSAccessManager-recognized new name of the file.
   */
  public function __construct(FSAccessManager $fs_access, $id, $operation, $source, $destination = NULL) {
    parent::__construct($id);
    $this->fs_access = $fs_access;
    $this->operation = $operation;
    $this->source = $source;
    $this->destination = $destination;
  }

  public function describe() {
    if ($this->operation == 'delete') {
      return sprintf('Deleting %s', $this->source);
    } else {
      return sprintf('Renaming %s to %s', $this->source, $this->destination);
    }
  }

  public function run(TaskInterface $task, TaskInstanceStateInterface $instance_state) {

  }

}
