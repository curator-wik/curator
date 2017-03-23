<?php


namespace Curator\AppTargeting;

/**
 * Interface AppTargeterFactoryInterface
 *   There are a variety of places where it is desirable to be able to pull
 *   an arbitrary AppTargeter service from the DI container by DI identifier.
 *   For example, this happens in the AppDetector service once it has
 *   ascertained what the adjoining application is.
 *
 *   Rather than feeding such services the entire DI container, to simplify
 *   testing they use this more easily test-doubled interface.
 */
interface AppTargeterFactoryInterface {
  /**
   * @param string $app_id
   * @return TargeterInterface
   */
  function getAppTargeterById($app_id);
}
