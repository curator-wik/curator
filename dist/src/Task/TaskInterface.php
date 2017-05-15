<?php


namespace Curator\Task;


interface TaskInterface {
  /**
   * @return string
   *   A path that will be redirected to in lieu of the default controller.
   */
  function getRoute();

  /**
   * @return string|NULL
   *   The DI service name of a class that processes Task properties.
   *
   *   Task properties are properties of the appropriate TaskInterface subclass
   *   as set by the integration script.
   *
   *   If the Task has no properties, may return NULL.
   */
  function getDecoderServiceName();
}
