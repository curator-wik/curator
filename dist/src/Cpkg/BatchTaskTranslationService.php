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
   * @var CpkgReaderInterface $cpkg_reader
   */
  protected $cpkg_reader;

  public function __construct(TargeterInterface $app_targeter, CpkgReaderInterface $cpkg_reader) {
    $this->app_targeter = $app_targeter;
    $this->cpkg_reader = $cpkg_reader;
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
    $this->cpkg_reader->validateCpkgStructure($path_to_cpkg);
    $this->validateCpkgIsApplicable($path_to_cpkg);

    /*
     * Up to two tasks may be scheduled per version, in this order, depending on
     * the contents of the cpkg:
     * 1. Deletions and renames
     * 2. Verbatim file writes and patches
     */


  }

  protected function validateCpkgIsApplicable($cpkg_path) {
    $cpkg_application = $this->cpkg_reader->getApplication($cpkg_path);
    if (strcasecmp($cpkg_application, $this->app_targeter->getAppName()) !== 0) {
      throw new \InvalidArgumentException(
        sprintf('The update package is for "%s", but you are running %s.',
          $cpkg_application,
          $this->app_targeter->getAppName()
        )
      );
    }

    $current_version = (string) $this->app_targeter->getCurrentVersion();

    if ($this->cpkg_reader->getVersion($cpkg_path) === $current_version) {
      throw new \InvalidArgumentException(sprintf('The update package provides version "%s", but it is already installed.', $current_version));
    }

    $prev_versions = $this->cpkg_reader->getPrevVersions($cpkg_path);
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
}
