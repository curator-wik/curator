<?php


namespace Curator\Batch;
use mbaynton\BatchFramework\RunnableInterface;

/**
 * Interface DescribedRunnableInterface
 *   A Runnable with a method producing a natural language string describing
 *   what the Runnable will do.
 */
interface DescribedRunnableInterface extends RunnableInterface {
  /**
   * A short description of what the Runnable does. Verbs should be in the
   * present tense.
   *
   * Example: "Uploading the file /tmp/foo"
   *
   * @return string
   */
  public function describe();
}
