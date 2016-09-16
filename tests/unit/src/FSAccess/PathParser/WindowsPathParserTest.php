<?php
/**
 * Created by PhpStorm.
 * User: mbaynton
 * Date: 8/13/16
 * Time: 11:53 AM
 */

namespace Curator\Tests\FSAccess\PathParser;


use Curator\FSAccess\PathParser\WindowsPathParser;

class WindowsPathParserTest extends \PHPUnit_Framework_TestCase {
  /**
   * System under test factory.
   *
   * @return \Curator\FSAccess\PathParser\WindowsPathParser
   */
  protected static function sutFactory() {
    return new WindowsPathParser();
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
      $sut->pathIsAbsolute(' c:\\'),
      'Space in front of a drive specifier is not absolute.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('\\'),
      'Backslash is not absolute.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('\\stuff'),
      'Backslash is not absolute.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('\\\\stuff'),
      'Almost-formed UNC paths are not absolute'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('\\\\\\share'),
      'UNC paths missing a server name are not absolute.'
    );

    static::assertFalse(
      $sut->pathIsAbsolute('stuff\\that\\later\\has\\c:\\a\\drive\\specifier'),
      'Drive specifiers within the path do not make it absolute.'
    );
  }

  public function testAbsolutePaths() {
    $sut = static::sutFactory();
    static::assertTrue(
      $sut->pathIsAbsolute('c:\\'),
      'c:\\ is absolute.'
    );

    static::assertTrue(
      $sut->pathIsAbsolute('U:\\'),
      'U:\\ is absolute.'
    );

    static::assertTrue(
      $sut->pathIsAbsolute('\\\\server\\share'),
      'UNC paths are absolute'
    );

    static::assertTrue(
      $sut->pathIsAbsolute('\\\\?\\server\\share'),
      'long-name prefixed UNC paths are absolute'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('c:\\'),
      'c:\\'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('c:\\stuff'),
      'c:\\'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('U:\\things\\in\\places'),
      'U:\\'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('\\\\things\\in\\places'),
      '\\\\things\\'
    );

    static::assertEquals(
      $sut->getAbsolutePrefix('\\\\?\\things\\in\\places'),
      '\\\\?\\things\\in\\places'
    );

  }

}