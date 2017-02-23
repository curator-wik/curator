<?php


namespace Curator\Cpkg;
use Curator\AppTargeting\TargeterInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

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
    $reader = new ArchiveFileReader($path_to_cpkg);
    $this->validateCpkgStructure($reader);
    $this->validateCpkgIsApplicable($reader);

    /*
     * Up to two tasks may be scheduled per version, in this order, depending on
     * the contents of the cpkg:
     * 1. Deletions and renames
     * 2. Verbatim file writes and patches
     */


  }

  protected function validateCpkgIsApplicable(ArchiveFileReader $reader) {
    $cpkg_application = trim($reader->getContent('application'));
    if (strcasecmp($cpkg_application, $this->app_targeter->getAppName()) !== 0) {
      throw new \InvalidArgumentException(
        sprintf('The update package is for "%s", but you are running %s.',
          $cpkg_application,
          $this->app_targeter->getAppName()
        )
      );
    }

    $current_version = (string) $this->app_targeter->getCurrentVersion();

    if ($this->getVersion($reader) === $current_version) {
      throw new \InvalidArgumentException(sprintf('The update package provides version "%s", but it is already installed.', $current_version));
    }

    $prev_versions = $this->getPrevVersions($reader);
    if (! in_array($current_version, $prev_versions)) {
      if (count($prev_versions) == 1) {
        $supported_range = 'version ' . reset($prev_versions);
      } else {
        $supported_range = sprintf('versions %s through %s', reset($prev_versions), end($prev_versions));
      }
      throw new \InvalidArgumentException(
        sprintf('The update package does not contain updates to your version of %s. You are running version %s; the package updates %s.',
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
  protected function validateCpkgStructure(ArchiveFileReader $reader) {
    $required_files = [
      'application' => '/.+/',
      'package-format-version' => '/^(1\.0)?\s+$/',
      'version' => '|^[^\0\n/]+$|',
      'prev-versions-inorder' => '%^(?:[^\0\n/]+\n)*[^\0\n/]+\n*$%'
    ];

    foreach ($required_files as $filename => $valid_pattern) {
      try {
        if (! preg_match($valid_pattern, $reader->getContent($filename))) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Data in %s file is corrupt or unsupported.', $filename));
        }
      } catch (FileNotFoundException $e) {
        throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Required file "%s" is absent from the cpkg structure.', $filename), 0, $e);
      }
    }

    $required_directories = ['payload'];
    $prev_versions = $this->getPrevVersions($reader);
    array_shift($prev_versions);
    foreach ($prev_versions as $prev_version) {
      $required_directories[] = sprintf('payload/%s', $prev_version);
    }
    $version = $this->getVersion($reader);
    $required_directories[] = sprintf('payload/%s', $version);

    foreach ($required_directories as $required_directory) {
      if (! $reader->isDir($required_directory)) {
        if ($reader->isFile($required_directory)) {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is not a directory.', $required_directory));
        } else {
          throw new \UnexpectedValueException(sprintf('Provided cpkg is invalid: Update payload directory "%s" is absent.', $required_directory));
        }
      }
    }
  }

  protected function getVersion(ArchiveFileReader $reader) {
    if (! array_key_exists($reader->getArchivePath(), $this->versionCache)) {
      $version = trim($reader->getContent('version'));
      if ($version === '') {
        throw new \LogicException('Newest version in cpkg not found.');
      }
      $this->versionCache[$reader->getArchivePath()] = $version;
    }

    return $this->versionCache[$reader->getArchivePath()];
  }

  /**
   * @param \PharData $phar
   * @return array
   *   An indexed array of previous versions supported by the cpkg, in the
   *   order listed in the prev-versions-inorder file (that is, earliest to
   *   latest.)
   */
  protected function getPrevVersions(ArchiveFileReader $reader) {
    if (! array_key_exists($reader->getArchivePath(), $this->prevVersionsCache)) {
      $prev_versions = $reader->getContent('prev-versions-inorder');
      $prev_versions = explode("\n", $prev_versions);
      $prev_versions = array_filter($prev_versions, function($v) {
        return trim($v) !== '';
      });

      if (count($prev_versions) == 0) {
        // Should not happen because validateCpkg should have rejected.
        throw new \LogicException('No previous versions found.');
      }
      $this->prevVersionsCache[$reader->getArchivePath()] = $prev_versions;
    }

    return $this->prevVersionsCache[$reader->getArchivePath()];
  }
}
