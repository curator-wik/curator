<?php


namespace Curator\Task;


interface TaskInterface {
  /**
   * @return callable
   */
  function getController();
}
