<?php
namespace Curator\Cpkg;


/**
 * Class CpkgReader
 *   Service offering an API for easy traversal of the cpkg format.
 */
interface CpkgReaderInterface {
  function validateCpkgStructure($cpkg_path);

  function getVersion($cpkg_path);

  /**
   * @param string $cpkg_path ;
   *   Path to an archive file containing a cpkg structure.
   * @return array
   *   An indexed array of previous versions supported by the cpkg, in the
   *   order listed in the prev-versions-inorder file (that is, earliest to
   *   latest.)
   */
  function getPrevVersions($cpkg_path);

  /**
   * @param string $cpkg_path
   *   Path to an archive file containing a cpkg structure.
   * @return string
   *   The application name the cpkg identifies itself as for.
   */
  function getApplication($cpkg_path);

  /**
   * @param string $cpkg_path
   *   Path to an archive file containing a cpkg structure.
   * @param string $version
   *   Version of interest.
   * @return string[]
   *   An associative array of files renamed in $version since the previous
   *   version, in the order they appear in the cpkg's rename file.
   *   Keys are the files' original names, values are new names.
   */
  function getRenames($cpkg_path, $version);

  /**
   * @param string $cpkg_path
   *   Path to an archive file containing a cpkg structure.
   * @param string $version
   *   Version of interest.
   * @return string[]
   *   An indexed array of paths to be deleted, relative to the component root.
   */
  function getDeletes($cpkg_path, $version);
}
