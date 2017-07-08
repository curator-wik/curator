<?php


namespace Curator\Task;


use Curator\AppTargeting\TargeterInterface;

class UpdateTask implements TaskInterface {

  /**
   * @var string $component
   * The name of the component to update, as identified in the cpkg.
   */
  protected $component;

  /**
   * @var string $source_spec
   * A URL to the update cpkg.
   */
  protected $source_spec;

  /**
   * @var string $to_version
   * The version identifier to update to, as identified in the cpkg.
   */
  protected $to_version;



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
    $this->component = $component_name;
    return $this;
  }

  /**
   * @param $source_spec
   *   URI (local or remote) to an update package.
   * @return $this
   */
  public function fromPackage($source_spec) {
    $this->source_spec = $source_spec;
    return $this;
  }

  /**
   * @param string $version
   *
   * @return $this
   */
  public function toVersion($version) {
    $this->to_version = $version;
    return $this;
  }

  public function getRoute() {
    return 'init-update';
  }

  /**
   * Gets the name of the service registered with DI to interpret this task.
   *
   * @return string
   */
  public function getDecoderServiceName() {
    return 'Update controller';
  }

  /**
   * @return string
   */
  public function getComponent() {
    return $this->component;
  }

  /**
   * @return string
   */
  public function getPackageLocation() {
    return $this->source_spec;
  }

  /**
   * @return string
   */
  public function getToVersion() {
    return $this->to_version;
  }

}
