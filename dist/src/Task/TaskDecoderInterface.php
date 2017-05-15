<?php


namespace Curator\Task;


interface TaskDecoderInterface {
  /**
   * Performs any examination and processing of $task properties that is
   * necessary prior to redirecting to the TaskInterface's route.
   *
   * @param \Curator\Task\TaskInterface $task
   *
   * @return mixed|null
   *   Generally, this method should only mutate persistent state, and return
   *   no value. However, if a value is returned, then it is assumed the useful
   *   work related to this request was entirely performed by the task decoder.
   *   In this case, the non-null return value is passed back to the integration
   *   script as the return value of AppManager::run().
   */
  function decodeTask(TaskInterface $task);
}
