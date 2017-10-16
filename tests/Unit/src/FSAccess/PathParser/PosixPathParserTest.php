<?php


namespace Curator\Tests\Unit\FSAccess\PathParser;


use Curator\FSAccess\PathParser\PosixPathParser;

class PosixPathParserTest extends \PHPUnit\Framework\TestCase {

  /**
   * System under test factory.
   *
   * @return \Curator\FSAccess\PathParser\PosixPathParser
   */
  protected static function sutFactory() {
    return new PosixPathParser();
  }

  public function testRelativePaths() {
    $sut = static::sutFactory();
    static::assertFalse(
      $sut->pathIsAbsolute('a/b/c'),
      'Relative paths are not identified as absolute.'
    );

    static::assertFalse(
      $sut->getAbsolutePrefix('a/b/c'),
      'Relative paths have no absolute prefix.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute(''),
      'The empty string is not considered an absolute path.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute(' /'),
      'Space in front of / is not absolute.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('\\'),
      'Backslash is not absolute'
    );
  }

  public function testAbsolutePaths() {
    $sut = static::sutFactory();
    static::assertTrue(
      $sut->pathIsAbsolute('/'),
      'Root path is absolute.'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('/'),
      '/'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('/a'),
      '/'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('/a/b'),
      '/'
    );
  }
}
