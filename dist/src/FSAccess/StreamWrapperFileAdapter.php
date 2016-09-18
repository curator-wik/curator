<?php
/**
 * Created by PhpStorm.
 * User: mbaynton
 * Date: 9/8/16
 * Time: 11:39 AM
 */

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

}
