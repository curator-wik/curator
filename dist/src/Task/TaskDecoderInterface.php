<?php


namespace Curator\Task;


interface TaskDecoderInterface {
  /**
   * Performs any examination and processing of $task properties that is
   * necessary prior to redirecting to the TaskInterface's route.
   *
   * @param \Curator\Task\TaskInterface $task
   * @return void
   */
  function decodeTask(TaskInterface $task);
}
