<?php


namespace Curator\FSAccess;


abstract class AbstractFSAccess implements FSAccessInterface {

  /**
   * @var string $workingPath
   */
  protected $workingPath;

  public function construct() {
    $this->workingPath = NULL;
  }

  /**
   * @inheritdoc
   *
   * @throws FileNotFoundException
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  public abstract function fileGetContents($filename);

  /**
   * @inheritdoc
   *
   * @throws FileNotFoundException
   *   If a directory in the path to $filename is not found.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $filename is outside the working path.
   */
  public abstract function filePutContents($filename, $data);

  /**
   * @inheritdoc
   *
   * @throws FileNotFoundException
   *   When the $old_name does not exist, or a directory in $new_name is not
   *   found.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $old_name or $new_name is outside the working path.
   */
  public abstract function mv($old_name, $new_name);

  /**
   * @inheritdoc
   *
   * @throws FileNotFoundException
   *   When a non-leaf directory of $path is not found and $create_parents is
   *   false.
   * @throws FileException
   *   Resulting from permission or I/O errors.
   * @throws \InvalidArgumentException
   *   If $path is outside the working path.
   */
  public abstract function mkdir($path, $create_parents = FALSE);

  public function __sleep() {
    return ['workingPath'];
  }

}
