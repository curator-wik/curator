<?php

namespace Curator\Cpkg;


/**
 * Class ArchiveFileReader
 *   At present, this class basically wraps PharData methods. It exists because
 *   I don't particularly like the storing of cpkg's in /tmp while they are
 *   getting extracted. This class acts as an abstraction layer to a future
 *   alternative means of handling archive file I/O.
 */
interface CpkgReaderPrimitivesInterface {

  /**
   * @return string
   *   The path passed to the constructor that references the archive file.
   */
  public function getArchivePath();

  /**
   * @param string $path
   *   A path within the archive file.
   *
   * @throws \Curator\FSAccess\FileNotFoundException
   *   When $path is not a regular file in the archive.
   *
   * @return string
   */
  public function getContent($path);

  public function tryGetContent($path, $default = '');

  public function isFile($path);

  public function isDir($path);

  /**
   * Gets an iterator over all files and directories below the given path.
   *
   * // TODO: develop our own recursive iterator that efficiently implements
   * // \SeekableIterator given an index of every n'th path. This could improve
   * // batch runner incarnation startup time on very large archives.
   *
   * @param string $internal_path
   *   The path within the archive to iterate all descendants of.
   *   The empty string will result in an iterator over the entire contents.
   *
   * @return \Iterator
   */
  public function getRecursiveFileIterator($internal_path = '');
}