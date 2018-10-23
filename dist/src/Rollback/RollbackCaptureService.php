<?php


namespace Curator\Rollback;


use Curator\AppTargeting\AppDetector;
use Curator\FSAccess\FileExistsException;
use Curator\FSAccess\FileNotFoundException;
use Curator\FSAccess\FSAccessManager;

/**
 * Class RollbackCaptureService
 *
 * Service ID: rollback
 *
 * This service is invoked by and works in concert with the runnables that apply cpkg updates.
 * The idea is to create a directory structure at a writable scratch location that is itself more or
 * less a cpkg that, if applied, would result in the reversal of the updates applied so far.
 *
 * As a simplification, although the service accepts all change types supported by cpkgs as incoming changes
 * (so, including file renames and patch application), when crafting the reversal cpkg it limits itself to
 * "write entire file at location" and "delete file at location" operations to restore the original state.
 *
 * The devil is in the details. The details are obscure edge cases, but they inform the overall design.
 * - This service depends on an FSAccessManager, but it is not necessarily the fs_access DI container service
 *   that's used everywhere else. This is because that one is equipped to write to the live code areas of the
 *   site, which might mean that its write adapter is ftp or somesuch. For capturing rollback data, we just need
 *   to write somewhere reasonably private. So we assume there's always somewhere we can write to directly via
 *   mounted filesystems that isn't getting served out over the Internet, and use an FSAccessManager that's
 *   always instantiated with the mounted filesystems write adapter. That affords us some small efficiency gains
 *   whenever we need to preserve the original version of a file that's about to be overwritten: we can issue a
 *   move from its original home to the rollback capture location rather than copying it (maybe via ftp...ick).
 *   That's arguably unnecessary optimization, except that we need to support the potential for update cpkgs that
 *   instruct us to delete paths that represent large directory trees. This requires getting the whole contents
 *   of the doomed directory into the rollback capture location; at that point it becomes worth it to just mv
 *   the directory -- for most sites where their private file location is on the same filesystem as the live code,
 *   we can keep that an O(1) deal.
 *
 * - The batch framework's concurrent deployment of the update cpkg prevent us from safely writing another cpkg
 *   on-the-fly that captures the rollbacks. We assume that most often, updates apply cleanly, and optimize for
 *   performance in the case where nothing goes wrong. The update deployment runners are thus left to operate
 *   concurrently and independently, and where this prevents us from creating a perfect cpkg we get as close as
 *   we can and clean it up in a post-processing step that only runs if a rollback becomes necessary.
 *   Areas where we deviate from a correct cpkg:
 *     * The "deleted_files" metadata file is a shared resource that several runners might try to add to. For
 *       increased robustness and portability we just create several files that collectively contain this
 *       information.
 *     * The rollback for the case where a file is renamed and patched in the same update is not captured correctly:
 *       renames and patches are entirely different tasks that are unaware of each other. Renames create a "write a
 *       file containing the original contents at the original location" instruction in the rollback capture area as
 *       well as a "delete the file at the rename destination" instruction. Patches create a "write a file containing
 *       the original contents at the file location" in the rollback capture area, which for renamed files is the
 *       rename destination. So we end up with an instruction to delete a file at the rename destination and also
 *       to write a file at the rename destination in the rollback capture area when a rename + patch occurs.
 *       This telltale sign of a write and a delete at the same path is explicitly searched for in post-processing,
 *       and resolved by removing the write (thus preventing two copies of the original from being restored.)
 */
class RollbackCaptureService implements RollbackCaptureInterface
{
  /**
   * @var FSAccessManager $fs
   */
  protected $fs;

  /**
   * @var string[] $payloadPathCache
   *   The capture service auto-learns the location within the rollback capture directory
   *   where it should place things based on the contents of the version file in the
   *   capture directory. This cache keeps those.
   */
  protected $payloadPathCache;

  /**
   * @var AppDetector $appDetector
   */
  protected $appDetector;

  /**
   * RollbackCaptureService constructor.
   * @param FSAccessManager $fs
   *   The FSAccessManager whose read and write adapters are both using mounted filesystems.
   */
  public function __construct(FSAccessManager $fs, AppDetector $appDetector)
  {
    $this->fs = $fs;
    $this->appDetector = $appDetector;
    $this->payloadPathCache = [];
  }

  public function initializeCaptureDir($captureDir) {
    try {
      $this->fs->mkdir($captureDir, TRUE);
    } catch (FileExistsException $e) {
      if ($e->getCode() == 0) {
        // 0 = it's already there. Make sure we're starting with a clean slate.
        $this->fs->rm($captureDir, TRUE);
        $this->fs->mkdir($captureDir);
      } else {
        throw $e;
      }
    }

    $captureDir = $this->fs->ensureTerminatingSeparator($captureDir);

    // TODO: component file; not needed unless we start doing modules.
    $appTargeter = $this->appDetector->getTargeter();
    $this->fs->filePutContents($captureDir . 'application', $appTargeter->getAppName());
    $this->fs->filePutContents($captureDir . 'package-format-version', '1.0');
    $this->fs->filePutContents($captureDir . 'version', 'rollback');
    $this->fs->filePutContents($captureDir . 'prev-versions-inorder', 'partial update');
    $payloadDir = $this->payloadPath($captureDir);
    $this->fs->mkdir($payloadDir, TRUE);
  }

  /**
   * Informs the rollback capture service of your intent to make a change.
   *
   * The necessary information to reverse the change is recorded as a result of this call.
   *
   * @param Change $change
   *   A Change object describing the change you intend to make.
   * @param string $captureDir
   *   The directory under which the CaptureService may record its changes.
   *   Original code files and metadata are copied here; it should be secure from the public.
   * @param string|int $runnerId
   *   Optional. When used with the batch framework, this causes a separate copy of some metadata
   *   files to be created per concurrent runner so as to avoid corruption or data loss from concurrent
   *   read/copy/write operations.
   */
  public function capture(Change $change, $captureDir, $runnerId = '') {
    switch ($change->getType()) {
      case Change::OPERATION_WRITE:
        if ($this->fs->isFile($change->getTarget()) || $this->fs->isDir($change->getTarget())) {
          $this->captureFile($change->getTarget(), TRUE, $captureDir);
        } else {
          $this->captureDelete($change->getTarget(), $captureDir, $runnerId);
        }
        break;
      case Change::OPERATION_MKDIRTREE:
        $this->captureDeletes($change->getTarget(), $captureDir, $runnerId);
        break;
      case Change::OPERATION_PATCH:
        // Similar to OPERATION_WRITE, but we'll assume there's a file there.
        // Current patch strategy is to patch an in-memory copy and rewrite whole file, so destructive is okay.
        $this->captureFile($change->getTarget(), TRUE, $captureDir);
        break;
      case Change::OPERATION_DELETE:
        $this->captureFile($change->getTarget(), TRUE, $captureDir);
        break;
      case Change::OPERATION_RENAME:
        /** @var ChangeTypeRename $change */
        $this->captureDelete($change->getTarget(), $captureDir, $runnerId);
        $this->captureFile($change->getSource(), FALSE, $captureDir);
        break;
    }
  }

  protected function captureFile($path, $destructive, $captureDir) {
    $capturePath = $this->payloadPath($captureDir);
    $destination = sprintf("%s%s%s",
      $capturePath,
      $this->fs->ensureTerminatingSeparator('files'),
      $path
    );

    // Ensure the full directory structure required to capture the file is present.
    try {
      $this->fs->mkdir($this->fs->ensureTerminatingSeparator($destination) . '..', TRUE);
    } catch (FileExistsException $e) {
      if ($e->getCode() != 0) {
        throw $e;
      }
    }

    try {
      if ($destructive) {
        // Should be safe even when $path is a directory since we know backend is mounted fs
        $this->fs->mv($path, $destination);
      } else {
        if ($this->fs->isDir($path)) {
          $this->fs->mkdir($destination);
        } else {
          $this->fs->filePutContents($destination, $this->fs->fileGetContents($path));
        }
      }
    } catch (FileNotFoundException $e) {
      // The fs methods might throw this due to the $destination's dirtree being incomplete or
      // due to the source file at $path being absent. We assume the former isn't the case
      // because we just ensured the directory structure was present, therefore it must be that
      // $path is not there.
      // This is actually not necessarily cause for alarm, because a common user of this
      // function is DeleteRenameBatchRunnable's delete(), which currently removes files and
      // directories in an undefined order with the possibility of several concurrent runners
      // trying to delete the same file (see github issue #5 for discussion.) If another runner
      // beat us to removing $path, then it will exist now at $destination, and in that case we
      // can safely swallow this exception.
      if (! $this->fs->isFile($destination) &&  ! $this->fs->isDir($destination)) {
        throw $e;
      }
    }
  }

  protected function captureDelete($path, $captureDir, $runnerId) {
    $this->captureDeletes([$path], $captureDir, $runnerId);
  }

  protected function captureDeletes($paths, $captureDir, $runnerId) {
    $capturePath = $this->payloadPath($captureDir);
    $deletesFile = $capturePath . 'deleted_files';
    if ($runnerId !== null) {
      $deletesFile .= '.' . $runnerId;
    }

    try {
      $currentDeletes = $this->fs->fileGetContents($deletesFile);
    } catch (FileNotFoundException $e) {
      // First time.
      $currentDeletes = '';
    }

    $currentDeletes .= implode("\n", $paths) . "\n";
    $this->fs->filePutContents($deletesFile, $currentDeletes);
  }

  protected function payloadPath($captureDir) {
    if (empty($this->payloadPathCache[$captureDir])) {
      $rollbackSyntheticVersion = $this->fs->fileGetContents(
        $this->fs->ensureTerminatingSeparator($captureDir) . 'version'
      );
      $this->payloadPathCache[$captureDir] =
        $this->fs->ensureTerminatingSeparator($captureDir) .
        $this->fs->ensureTerminatingSeparator('payload') .
        $this->fs->ensureTerminatingSeparator($rollbackSyntheticVersion);
    }

    return $this->payloadPathCache[$captureDir];
  }

  /**
   * Transforms the almost-cpkg rollback capture directory into a correct cpkg structure.
   *
   * @param string $captureDir
   */
  public function fixupToCpkg($captureDir) {
    $capturePath = $this->payloadPath($captureDir);
    $deleted_things = $this->fixupDeletes($capturePath);
    $this->fixupRenamePatch($capturePath, $deleted_things);
  }

  protected function fixupDeletes($capturePath) {
    // combines all deleted_files.* files into deleted_files
    $listing = $this->fs->ls($capturePath);
    $buffer = [];
    foreach ($listing as $inode_name) {
      if (strpos($inode_name, 'deleted_files') === 0) {
        $buffer = array_merge($buffer, explode("\n", $this->fs->fileGetContents($capturePath . $inode_name)));
      }
    }

    $this->fs->filePutContents($capturePath . 'deleted_files', implode("\n", $buffer));
    return $buffer;
  }

  protected function fixupRenamePatch($capturePath, $deleted_things) {
    // Search for paths that are both being deleted and written; remove the write.
    // See comment block at top.
    $captured_writes_path = $this->fs->ensureTerminatingSeparator($capturePath . 'files');
    foreach ($deleted_things as $path) {
      $check = $captured_writes_path . $path;
      if ($this->fs->isFile($check)) {
        $this->fs->rm($check);
      }
    }
  }
}
