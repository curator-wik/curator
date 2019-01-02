<?php


namespace Curator\Cpkg;

use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\ReadAdapterInterface;
use Curator\FSAccess\WriteAdapterInterface;

/**
 * Class CpkgReader
 *   Service offering an API for easy traversal of the cpkg format.
 */
class CpkgReader implements CpkgReaderInterface {

  /**
   * @var array $structureValidationCache
   *   Exceptions keyed by phar path.
   */
  protected $structureValidationCache = [];

  /**
   * @var array $versionCache
   *   Keyed by phar path.
   */
  protected $versionCache = [];

  /**
   * @var array $prevVersionsCache
   *   Keyed by phar path.
   */
  protected $prevVersionsCache = [];

  /**
   * @var array $applicationNameCache
   *   Application names keyed by phar path.
   */
  protected $applicationNameCache = [];

  /**
   * @var array $renamesCache
   *   Rename operations keyed by a phar path and version compound key.
   */
  protected $renamesCache = [];

  /**
   * @var array $deletesCache
   *   Delete operations keyed by a phar path and version compound key.
   */
  protected $deletesCache = [];

  /**
   * The character in renamed_files and deleted_files that separates entries.
   * v1.0 of cpkg spec only provides for newline, but future versions may
   * provide the option for e.g. the null byte.
   *
   * @var string $entry_delimiter
   */
  protected $entry_delimiter = "\n";

  /**
   * @var ReadAdapterInterface $fsAccessReader
   */
  protected $fsAccessReader;

  /**
   * @var WriteAdapterInterface $fsAccessWriter
   */
  protected $fsAccessWriter;

  /**
   * CpkgReader constructor.
   *
   * @param \Curator\FSAccess\ReadAdapterInterface $fsAccessReader
   *   Reader used when the underlying cpkg is not a phar=compatible archive.
   * @param \Curator\FSAccess\WriteAdapterInterface $fsAccessWriter
   *   Writer accompanying the reader to create an FSAccessManager.
   */
  public function __construct(ReadAdapterInterface $fsAccessReader, WriteAdapterInterface $fsAccessWriter) {
    $this->fsAccessReader = $fsAccessReader;
    $this->fsAccessWriter = $fsAccessWriter;
  }

  /**
   * Selects a CpkgReaderPrimitivesInterface implementation for a given cpkg.
   *
   * @param $cpkg_path
   *
   * @return \Curator\Cpkg\CpkgReaderPrimitivesInterface
   */
  public function getReaderPrimitives($cpkg_path) {
    if (is_dir($cpkg_path)) {
      return new FSAccessReader($cpkg_path, $this->fsAccessReader, $this->fsAccessWriter);
    } else {
      return new ArchiveFileReader($cpkg_path);
    }
  }

  public function validateCpkgStructure($cpkg_path) {
    if (array_key_exists($cpkg_path, $this->structureValidationCache)) {
      if ($this->structureValidationCache[$cpkg_path] !== NULL) {
        throw $this->structureValidationCache[$cpkg_path];
      } else {
        return;
      }
    }

    try {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $required_files = [
        'application' => '/.+/',
        'package-format-version' => '/^(1\.0)?\s*$/',
        'version' => '|^[^\0\n/]+$|',
        'prev-versions-inorder' => '%^(?:[^\0\n/]+\n)*[^\0\n/]+\n*$%'
      ];

      foreach ($required_files as $filename => $valid_pattern) {
        try {
          if (!preg_match($valid_pattern, $reader->getContent($filename))) {
            throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Data in %s file is corrupt or unsupported.', $filename));
          }
        } catch (FileNotFoundException $e) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Required file "%s" is absent from the cpkg structure.', $filename), 0, $e);
        }
      }

      $required_directories = ['payload'];
      $prev_versions = $this->_getPrevVersions($cpkg_path, TRUE);
      array_shift($prev_versions);
      foreach ($prev_versions as $prev_version) {
        $required_directories[] = sprintf('payload/%s', $prev_version);
      }
      $version = $this->_getVersion($cpkg_path, TRUE);
      $required_directories[] = sprintf('payload/%s', $version);

      foreach ($required_directories as $required_directory) {
        if (!$reader->isDir($required_directory)) {
          if ($reader->isFile($required_directory)) {
            throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is not a directory.', $required_directory));
          }
          else {
            throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is absent.', $required_directory));
          }
        }
      }

      // TODO: Verify that within a version, no file is both deleted and renamed (from or to). Results would be undefined.
    } catch (\Exception $e) {
      $this->structureValidationCache[$cpkg_path] = $e;
      throw $e;
    }
    $this->structureValidationCache[$cpkg_path] = NULL;
  }

  public function getVersion($cpkg_path) {
    return $this->_getVersion($cpkg_path);
  }

  protected function _getVersion($cpkg_path, $skip_structural_validation = FALSE) {
    if (! $skip_structural_validation) {
      $this->validateCpkgStructure($cpkg_path);
    }

    if (! array_key_exists($cpkg_path, $this->versionCache)) {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $version = trim($reader->getContent('version'));
      if ($version === '') {
        throw new \LogicException('Newest version in cpkg not found.');
      }
      $this->versionCache[$cpkg_path] = $version;
    }

    return $this->versionCache[$cpkg_path];
  }

  /**
   * @param string $cpkg_path;
   *   Path to an archive file containing a cpkg structure.
   * @return array
   *   An indexed array of previous versions supported by the cpkg, in the
   *   order listed in the prev-versions-inorder file (that is, earliest to
   *   latest.)
   */
  public function getPrevVersions($cpkg_path) {
    return $this->_getPrevVersions($cpkg_path);
  }

  protected function _getPrevVersions($cpkg_path, $skip_structural_validation = FALSE) {
    if (! $skip_structural_validation) {
      $this->validateCpkgStructure($cpkg_path);
    }

    if (! array_key_exists($cpkg_path, $this->prevVersionsCache)) {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $prev_versions = $reader->getContent('prev-versions-inorder');
      $prev_versions = explode($this->entry_delimiter, $prev_versions);
      $prev_versions = array_filter($prev_versions, function($v) {
        return trim($v) !== '';
      });

      if (count($prev_versions) == 0) {
        // Should not happen because validateCpkg should have rejected.
        throw new \LogicException('No previous versions found.');
      }
      $this->prevVersionsCache[$cpkg_path] = $prev_versions;
    }

    return $this->prevVersionsCache[$cpkg_path];
  }

  public function getApplication($cpkg_path) {
    return $this->_getApplication($cpkg_path);
  }

  protected function _getApplication($cpkg_path, $skip_structural_validation = FALSE) {
    if (! $skip_structural_validation) {
      $this->validateCpkgStructure($cpkg_path);
    }

    if (! array_key_exists($cpkg_path, $this->applicationNameCache)) {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $this->applicationNameCache[$cpkg_path]
        = trim($reader->getContent('application'));
    }

    return $this->applicationNameCache[$cpkg_path];
  }

  public function getRenames($cpkg_path, $version) {
    return $this->_getRenames($cpkg_path, $version);
  }

  protected function _getRenames($cpkg_path, $version, $skip_structural_validation = FALSE) {
    if (! $skip_structural_validation) {
      $this->validateCpkgStructure($cpkg_path);
    }

    $cache_key = $cpkg_path . $version;
    if (! array_key_exists($cache_key, $this->renamesCache)) {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $renames = $reader->tryGetContent("payload/$version/renamed_files");
      $renames = array_filter(
        explode($this->entry_delimiter, $renames),
        function($line) {
          return trim($line) !== '';
        }
      );

      $this->renamesCache[$cache_key] = [];
      foreach ($renames as $line) {
        list($old_name, $new_name) = explode(' /// ', $line, 2);
        $old_name = trim($old_name);
        $new_name = trim($new_name);
        if (empty($old_name) || empty($new_name)) {
          throw new \UnexpectedValueException(sprintf("Encountered corrupted renamed_files data in version %s. Corrupt entry is:\n%s", $version, $line));
        }
        $this->renamesCache[$cache_key][$old_name] = $new_name;
      }
    }

    return $this->renamesCache[$cache_key];
  }

  public function getDeletes($cpkg_path, $version) {
    return $this->_getDeletes($cpkg_path, $version);
  }

  protected function _getDeletes($cpkg_path, $version, $skip_structural_validation = FALSE) {
    if (! $skip_structural_validation) {
      $this->validateCpkgStructure($cpkg_path);
    }

    $cache_key = $cpkg_path . $version;
    if (! array_key_exists($cache_key, $this->deletesCache)) {
      $reader = $this->getReaderPrimitives($cpkg_path);
      $deletes = $reader->tryGetContent("payload/$version/deleted_files");
      $deletes = array_filter(
        explode($this->entry_delimiter, $deletes),
        function($line) {
          return trim($line) !== '';
        }
      );
      $this->deletesCache[$cache_key] = $deletes;
    }
    return $this->deletesCache[$cache_key];
  }
}
