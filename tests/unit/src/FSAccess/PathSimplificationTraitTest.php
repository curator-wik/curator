<?php


namespace Curator\Tests\FSAccess;


use Curator\FSAccess\PathParser\GenericPathParser;
use Curator\FSAccess\PathParser\PathParserInterface;
use Curator\FSAccess\PathParser\PosixPathParser;
use Curator\FSAccess\PathParser\WindowsPathParser;
use Curator\FSAccess\PathSimplificationTrait;

class PathSimplificationTraitTest extends \PHPUnit_Framework_TestCase {
  use PathSimplificationTrait;

  /**
   * @var PathParserInterface $pathParser
   */
  protected $pathParser;

  public function setUp() {
    // Default to a PosixPathParser
    $this->setPathParser(new PosixPathParser());
  }

  protected function setPathParser(PathParserInterface $parser) {
    $this->pathParser = $parser;
  }

  protected function getPathParser() {
    return $this->pathParser;
  }

  public function testRemovesSequentialDirectorySeparators() {
    $this->setPathParser(new GenericPathParser());
    self::assertEquals(
      "there's/no/place/like/home",
      $this->simplifyPath("there's\\no//place/\\like\\\\home"),
      'Sequential directory separators are reduced to one and homogenized to "/".'
      );
  }

  public function testStripsCurrDirReferences() {
    $this->setPathParser(new GenericPathParser());
    self::assertEquals(
      "there's/no/place/like/home",
      $this->simplifyPath("there's/./no\\.\\place//./like\\.//home/././"),
      'References to the current directory are removed.'
    );
  }

  public function testUnrollsParentReferences() {
    self::assertEquals(
      "there's/no/place/like/home",
      $this->simplifyPath("there's/no/wifi/at/this/../../../place/just/../like/home"),
      'Parent directory references within the path are unrolled.'
    );

    self::assertEquals(
      "/root/one/two",
      $this->simplifyPath("/blergh/../root/one/two"),
      'Parent directory references on absolute paths going to depth 0 are unrolled.'
    );

    self::assertEquals(
      "one/two",
      $this->simplifyPath("blergh/../one/two")
    );

    self::assertEquals(
      "./../thingOne/and/thingTwo",
      $this->simplifyPath("/its/../../thingOne/and/thingTwo"),
      'Parent directory references on absolute paths going to negative depth result in a relative path.'
    );

    self::assertEquals(
      'relative',
      $this->simplifyPath("relative/path/../")
    );

    self::assertEquals(
      '.',
      $this->simplifyPath("relative/path/../../")
    );

    self::assertEquals(
      '/',
      $this->simplifyPath("/absolute/path/../../")
    );

    self::assertEquals(
      './..',
      $this->simplifyPath("relative/path/../../../")
    );

    self::assertEquals(
      './../negative1',
      $this->simplifyPath("relative/path/../../../negative1")
    );

    self::assertEquals(
      './../../thingOne/and/thingTwo',
      $this->simplifyPath("relative/path/../../../negative1/../../thingOne/and/thingTwo")
    );

    self::assertEquals(
      './../quick/brown/jumps',
      $this->simplifyPath("the/../../quick/brown/fox/../jumps/")
    );

    self::assertEquals(
      './../quick/kittens!',
      $this->simplifyPath("the/../../quick/brown/fox/../jumps/../../kittens!")
    );

    self::assertEquals(
      './../kittens!',
      $this->simplifyPath("the/../../quick/brown/fox/../jumps/../../../kittens!")
    );
  }

  public function testWindowsAbsolutePaths() {
    // Might want to employ ReadAdapterInterface's pathIsAbsolute functionality
    $this->setPathParser(new WindowsPathParser());
    self::assertEquals(
      "c:\\windows\\system32",
      $this->simplifyPath("c:\\windows\\system\\..\\system32"),
      'Drive-letter style Windows absolute paths are preserved.'
    );

    self::assertEquals(
      "\\\\big.server\\share\\things",
      $this->simplifyPath("\\\\big.server\\share\\stuff\\..\\things"),
      'UNC-style Windows absolute paths are preserved.'
    );
  }

  public function testDotHandling() {
    // Verifies that no unsecaped .'s that match any char snuck into regexes.
    self::assertEquals(
      "there's/1/place/like/home",
      $this->simplifyPath("there's/1/place/like/home"),
      'Single-character non-dot directories are not stripped out'
    );

    // Because we can.
    self::assertEquals(
      "there's/no/place/like/127.0.0.1.",
      $this->simplifyPath("there's/no/place/like/127.0.0.1./"),
      'Dots in regular directory names are not affected'
    );
  }
}
