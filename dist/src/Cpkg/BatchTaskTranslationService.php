<?php


namespace Curator\Cpkg;

/**
 * Class BatchTaskTranslationService
 *   Evaluates a cpkg archive structure and builds the necessary batch tasks
 *   to apply the archive.
 */
class BatchTaskTranslationService {
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
   * @param string $path_to_cpkg
   *
   * @throws \UnexpectedValueException
   *   When the $path_to_cpkg does not reference a valid cpkg archive.
   */
  public function makeBatchTasks($path_to_cpkg) {
    /*
     * Up to two tasks may be scheduled, in this order, depending on the
     * contents of the cpkg:
     * 1. Deletions and renames
     * 2. Verbatim file writes and patches
     */
    $phar = new \PharData($path_to_cpkg);
    $phar_path = $phar->getPath();
    if (empty($phar_path)) {
      throw new \LogicException('Missing path of already-opened cpkg.');
    }
    $this->validateCpkg($phar);
  }

  /**
   * @param \PharData $phar
   * @throws \UnexpectedValueException
   */
  protected function validateCpkg(\PharData $phar) {
    $required_files = [
      'application' => '/.+/',
      'package-format-version' => '/^(1\.0)?\s+$/',
      'version' => '|^[^\0\n/]+$|',
      'prev-versions-inorder' => '%^(?:[^\0\n/]+\n)*[^\0\n/]+\n*$%'
    ];

    foreach ($required_files as $filename => $valid_pattern) {
      try {
        /**
         * @var \PharFileInfo $finfo
         */
        $finfo = $phar[$filename];
        if (! preg_match($valid_pattern, $finfo->getContent())) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Data in %s file is corrupt or unsupported.'));
        }
      } catch (\BadMethodCallException $e) {
        throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Required file %s is absent from the cpkg structure.', $filename), 0, $e);
      }
    }

    $required_directories = ['payload'];
    $prev_versions = $this->getPrevVersions($phar);
    array_shift($prev_versions);
    foreach ($prev_versions as $prev_version) {
      $required_directories[] = sprintf('payload/%s', $prev_version);
    }
    $version = $this->getVersion($phar);
    $required_directories[] = sprintf('payload/%s', $version);

    foreach ($required_directories as $required_directory) {
      try {
        $finfo = $phar[$required_directory];
        if (! $finfo->isDir()) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is not a directory.', $required_directory));
        }
      } catch (\BadMethodCallException $e) {
        throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is absent.', $required_directory), 0, $e);
      }
    }
  }

  protected function getVersion(\PharData $phar) {
    if (! array_key_exists($phar->getPath(), $this->versionCache)) {
      $version = trim($phar['version']->getContent());
      if ($version === '') {
        throw new \LogicException('Newest version in cpkg not found.');
      }
      $this->versionCache[$phar->getPath()] = $version;
    }

    return $this->versionCache[$phar->getPath()];
  }

  /**
   * @param \PharData $phar
   * @return array
   *   An indexed array of previous versions supported by the cpkg, in the
   *   order listed in the prev-versions-inorder file (that is, earliest to
   *   latest.)
   */
  protected function getPrevVersions(\PharData $phar) {
    if (! array_key_exists($phar->getPath(), $this->prevVersionsCache)) {
      $prev_versions = $phar['prev-versions-inorder']->getContent();
      $prev_versions = explode("\n", $prev_versions);
      $prev_versions = array_filter($prev_versions, function($v) {
        return trim($v) !== '';
      });

      if (count($prev_versions) == 0) {
        // Should not happen because validateCpkg should have rejected.
        throw new \LogicException('No previous versions found.');
      }
      $this->prevVersionsCache[$phar->getPath()] = $prev_versions;
    }

    return $this->prevVersionsCache[$phar->getPath()];
  }
}
