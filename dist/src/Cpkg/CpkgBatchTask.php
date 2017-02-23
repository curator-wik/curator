<?php


namespace Curator\Cpkg;


use mbaynton\BatchFramework\Datatype\ProgressInfo;
use mbaynton\BatchFramework\RunnableInterface;
use mbaynton\BatchFramework\RunnableResultAggregatorInterface;
use mbaynton\BatchFramework\TaskInterface;

abstract class CpkgBatchTask implements TaskInterface, \Serializable {

  /**
   * @var string $cpkg_path
   */
  protected $cpkg_path;

  /**
   * @var string $version
   *   The version within the cpkg structure for this task to read from.
   */
  protected $version;

  public function __construct($cpkg_path, $version) {
    $this->cpkg_path = $cpkg_path;
  }

  protected function _getSerializedProperties() {
    return ['cpkg_path', 'version'];
  }

  public function serialize() {
    $data = [];
    foreach ($this->_getSerializedProperties() as $property) {
      $data[$property] = $this->$property;
    }
    return serialize($data);
  }

  public function unserialize($serialized) {
    $data = unserialize($serialized);
    foreach ($data as $key => $value) {
      $this->$key = $value;
    }

    $this->unserialized();
  }

  protected function unserialized() { /* override point */ }

  /**
   * Reduction not needed: Runnable results are not gathered.
   *
   * @return bool
   */
  public function supportsReduction() {
    return FALSE;
  }

  public function supportsUnaryPartialResult() {
    return FALSE;
  }

  public function reduce(RunnableResultAggregatorInterface $aggregator) { }

  public function updatePartialResult($new, $current = NULL) { }

  public function onRunnableComplete(RunnableInterface $runnable, $result, RunnableResultAggregatorInterface $aggregator, ProgressInfo $progress) { }

  public function onRunnableError(RunnableInterface $runnable, $exception, ProgressInfo $progress) {
    // TODO: Implement onRunnableError() method.
    // Look at adding a RunnableErrorAggregatorInterface parameter, and persisting those via the BatchFramework?
  }

}
