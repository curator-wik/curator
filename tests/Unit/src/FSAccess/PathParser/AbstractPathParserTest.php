<?php


namespace Curator\Tests\Unit\FSAccess\PathParser;


use Curator\FSAccess\PathParser\PathParserInterface;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;

class AbstractPathParserTest extends \PHPUnit_Framework_TestCase {

  /**
   * @param string $path
   *   The path to translate
   * @param \Curator\FSAccess\PathParser\PathParserInterface $from
   *   The PathParserInterface implementation corresponding to $path's format.
   * @param \Curator\FSAccess\PathParser\PathParserInterface $to
   *   The PathParserInterface implementation to apply translation rules of.
   * @param string $expected
   *   The expected translated path.
   * @return void
   */
  protected function translate($path, PathParserInterface $from, PathParserInterface $to, $expected) {
    static::assertEquals($expected,
      $from->translate($path, $to),
      get_class($from) . " path \"$path\" translates to " . get_class($to) . "path \"$expected\""
    );
  }

  public function testPosixToWindowsTranslation() {
    $p = new PosixPathParser();
    $w = new WindowsPathParser();

    $this->translate('.', $p, $w, '.');
    $this->translate('a', $p, $w, 'a');
    $this->translate('a/b', $p, $w, 'a\\b');
    $this->translate('.', $p, $w, '.');
    $this->translate('.', $p, $w, '.');
    $this->translate('.', $p, $w, '.');
  }

}
