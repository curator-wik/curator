<?php


namespace Curator\AppTargeting;


abstract class AbstractTargeter implements TargeterInterface {
  // TODO: use symfony translation component
  public abstract function getAppName();

  public abstract function getCurrentVersion();

  public abstract function getVariantTags();

  /**
   * Allows the Targeter to alter a file operation before it occurs.
   */
  public function fileOpAlter(/* FileOp $pending_operation */) {

  }
}
