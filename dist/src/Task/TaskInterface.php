<?php


namespace Curator\Task;


interface TaskInterface {
  /**
   * @return string
   *   The DI service name for the controller to use in handling this request.
   */
  function getControllerName();
}
