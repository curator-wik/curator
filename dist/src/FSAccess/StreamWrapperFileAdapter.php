<?php

namespace Curator\FSAccess;


use Curator\FSAccess\PathParser\PathParserInterface;

class StreamWrapperFileAdapter implements ReadAdapterInterface, WriteAdapterInterface {
  use ReadAdapterStreamWrapperTrait;
  use WriteAdapterStreamWrapperTrait;
  use CommonAdapterStreamWrapperTrait;

  /**
   * @var PathParserInterface $pathParser
   */
  protected $pathParser;

  /**
   * @var StreamContextWrapper $context
   */
  protected $context;

  /**
   * StreamWrapperFileAdapter constructor.
   * @param \Curator\FSAccess\PathParser\PathParserInterface $path_parser
   *   The path parser appropriate to the underlying filesystem.
   */
  public function __construct(PathParserInterface $path_parser) {
    $this->pathParser = $path_parser;

    $this->context = new StreamContextWrapper(
      stream_context_create(),
      'file://'
    );
  }

  public function getPathParser() {
    return $this->pathParser;
  }

  public function getAdapterName() {
    return 'host filesystem';
  }

  public function getStreamContext() {
    return $this->context;
  }

  protected function alterPathForStreamWrapper($path) {
    // No changes needed for host filesystem.
    return $path;
  }

  public function getCwd() {
    return getcwd();
  }

  /**
   * This function exists to help sleuth out why an operation on $path
   * has failed. It is necessary to sleuth because many PHP functions
   * such as file_get_contents(), is_file(), is_dir(), stat() don't
   * provide anything more than "it didn't work" as feedback themselves.
   * In the case of file_get/put_contents(), fopen() could possibly be
   * used instead and its E_WARNs intercepted, but this is not the
   * optimal way to transfer strings to/from files. (It might also not
   * work if the error strings vary by OS language...)
   *
   * Consequently, we end up making additional filesystem calls to a
   * failed path, which could have negative ramifications e.g. on
   * failing hardware, but I'm betting on low-tech user error being the
   * more common cause than h/w failure and opting to provide as much
   * detail as possible.
   *
   * @param $path
   * @param $read_write
   * @param $operation_description
   * @param \ErrorException|NULL $error_exception
   */
  protected function failPath($path, $read_write, $operation_description, \ErrorException $error_exception = NULL) {
    $probe_path = $path;
    $prev_probe_path = $probe_path;
    while (strncmp($probe_path, './', 2) !== 0) {
      if (file_exists($probe_path)) {
        if ($read_write == 'r') {
          if (!is_readable($probe_path)) {
            throw new FileException('Permission to read denied. ' . $operation_description, $path, 0, $error_exception);
          } else {
            throw new FileNotFoundException($prev_probe_path);
          }
        } else { // assume $read_write == 'w'.
          if (!is_writable($probe_path)) {
            throw new FileException('Permission to write denied. ' . $operation_description, $path, 0, $error_exception);
          } else {
            throw new FileNotFoundException($prev_probe_path);
          }
        }
      }
      $prev_probe_path = $probe_path;
      $probe_path = $this->simplifyPath($probe_path . '/..');
    }

    throw new FileException($operation_description, $path, 0, $error_exception);
  }
}
