<?php


namespace Curator\Cpkg;

/**
 * Class PatchFilterIterator
 *   Filters a FileIterator to only files with name *.patch.
 */
class PatchFilterIterator extends \FilterIterator {
  public function accept() {
    /**
     * @var \SplFileInfo $current
     */
    $current = parent::current();
    return substr($current->getFilename(), -6) == '.patch';
  }
}
