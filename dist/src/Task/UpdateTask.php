<?php


namespace Curator\Task;


use Curator\AppTargeting\TargeterInterface;

class UpdateTask implements TaskInterface {

  public function __construct($component) {
    $this->component($component);
  }

  /**
   * @param $component_name
   *   The name of the component (such as module or theme) to update.
   *
   * @return $this
   */
  public function component($component_name) {
    return $this;
  }

  /**
   * @param $source_spec
   *   URI (local or remote) to an update package.
   * @return $this
   */
  public function fromPackage($source_spec) {
    return $this;
  }

  public function getController() {
    return function () { return 'Update controller'; };
  }

}
