<?php


namespace Curator\Batch;

/**
 * Interface MessageCallbackRunnableInterface
 *
 * Interface that allows runners to post arbitrary update messages out to
 * the client at will during the Runnable's execution by executing a provided
 * callback.
 */
interface MessageCallbackRunnableInterface {
  /**
   * @param callable $callback
   *   A callback that the Runnable may invoke to send a BatchRunnerMessage
   *   to the client. The callback takes a single BatchRunnerMessage as an
   *   argument.
   *
   * @return void
   */
  function setUpdateMessageCallback(callable $callback);
}