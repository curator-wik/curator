<?php

namespace Curator\FSAccess;

/**
 * Class StreamContextWrapper
 *   A stream context resource, wrapped to ship some metadata about the
 *   context around with it - Unfortunately, there does not seem to be
 *   a way to inspect the resource itself.
 *
 *   This is not to be confused with "stream wrapper."
 */
class StreamContextWrapper
{
  /**
   * @var resource $context
   */
  protected $context;

  /**
   * @var string $scheme
   */
  protected $scheme;

  /**
   * @param resource $context
   *   The stream context.
   * @param $scheme
   *   The URL scheme this context is used with, e.g. 'file://' or 'ftp://'.
   */
  public function __construct($context, $scheme) {
    $this->context = $context;
    $this->scheme = $scheme;
  }

  /**
   * Gets the wrapped stream context.
   *
   * @return resource
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Gets the URL scheme of the stream wrapper this context targets.
   *
   * Separate contexts are used for separate stream wrappers.
   *
   * @return string
   */
  public function getScheme() {
    return $this->scheme;
  }
}
