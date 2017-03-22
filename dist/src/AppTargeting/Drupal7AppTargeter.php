<?php


namespace Curator\AppTargeting;


use Curator\FSAccess\FSAccessManager;
use Curator\IntegrationConfig;

class Drupal7AppTargeter extends AbstractTargeter {
  /**
   * @var string $site_root
   */
  protected $site_root;

  /**
   * @var FSAccessManager $fs_access
   */
  protected $fs_access;

  protected $current_version = NULL;

  public function __construct(IntegrationConfig $integration_config, FSAccessManager $fs_access) {
    $this->site_root = $integration_config->getSiteRootPath();
    $this->fs_access = $fs_access;
  }

  public function getAppName() {
    return 'Drupal 7.x';
  }

  public function getCurrentVersion() {
    if ($this->current_version === NULL) {
      // Go hunting.
      try {
        $system_info = parse_ini_string($this->fs_access->fileGetContents('modules/system/system.info'));
        if (! empty($system_info['version']) && preg_match('|^7\.\d+|', $system_info['version'])) {
          $this->current_version = $system_info['version'];
        } else {
          throw new \Exception('', 1);
        }
      } catch (\Exception $e) {
        // Try extracting it from bootstrap.inc instead.
        $bootstrap_code = $this->fs_access->fileGetContents('includes/bootstrap.inc');
        $matches = [];
        if (preg_match('|define\s*\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"](7\.\d+[^\'"]*)[\'"]\s*\)|', $bootstrap_code, $matches)) {
          $this->current_version = $matches[1];
        }
      }
    }

    return $this->current_version;
  }

  public function getVariantTags() {
    return [];
  }
}
