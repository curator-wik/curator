<?php


namespace Curator\Cpkg;
use Curator\AppTargeting\TargeterInterface;

/**
 * Class BatchTaskTranslationService
 *   Evaluates a cpkg archive structure and builds the necessary batch tasks
 *   to apply the archive.
 */
class BatchTaskTranslationService {

  /**
   * @var TargeterInterface $app_targeter
   */
  protected $app_targeter;

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

  public function __construct(TargeterInterface $app_targeter) {
    $this->app_targeter = $app_targeter;
  }

  /**
   * @param string $path_to_cpkg
   *
   * @throws \UnexpectedValueException
   *   When the $path_to_cpkg does not reference a valid cpkg archive.
   * @throws \InvalidArgumentException
   *   When the cpkg does not contain upgrades for the application.
   */
  public function makeBatchTasks($path_to_cpkg) {
    $phar = new \PharData($path_to_cpkg);
    $phar_path = $phar->getPath();
    if (empty($phar_path)) {
      throw new \LogicException('Missing path of already-opened cpkg.');
    }

    $this->validateCpkgStructure($phar);
    $this->validateCpkgIsApplicable($phar);

    /*
     * Up to two tasks may be scheduled per upgrade, in this order, depending on
     * the contents of the cpkg:
     * 1. Deletions and renames
     * 2. Verbatim file writes and patches
     */


  }

  protected function validateCpkgIsApplicable(\PharData $phar) {
    $cpkg_application = trim($phar['application']->getContent());
    if (strcasecmp($cpkg_application, $this->app_targeter->getAppName()) !== 0) {
      throw new \InvalidArgumentException(
        sprintf('The update package is for "%s", but you are running %s.',
          $cpkg_application,
          $this->app_targeter->getAppName()
        )
      );
    }

    $current_version = (string) $this->app_targeter->getCurrentVersion();

    if ($this->getVersion($phar) === $current_version) {
      throw new \InvalidArgumentException('The update the package provides has already been applied.');
    }

    $prev_versions = $this->getPrevVersions($phar);
    if (! in_array($current_version, $prev_versions)) {
      if (count($prev_versions) == 1) {
        $supported_range = 'version ' . reset($prev_versions);
      } else {
        $supported_range = sprintf('versions %s through %s', reset($prev_versions), end($prev_versions));
      }
      throw new \InvalidArgumentException(
        sprintf('The update package does not contain updates to your version of %s. You are running version %s; the package upgrades %s.',
          $this->app_targeter->getAppName(),
          $this->app_targeter->getCurrentVersion(),
          $supported_range
        )
      );
    }
  }

  /**
   * @param \PharData $phar
   * @throws \UnexpectedValueException
   */
  protected function validateCpkgStructure(\PharData $phar) {
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
        // Work around a segfault in php 5.4 when getting empty file content.
        $content = $finfo->getSize() === 0 ? '' : $finfo->getContent();
        if (! preg_match($valid_pattern, $content)) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Data in %s file is corrupt or unsupported.', $filename));
        }
      } catch (\BadMethodCallException $e) {
        throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Required file "%s" is absent from the cpkg structure.', $filename), 0, $e);
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
