<?php

namespace Curator\FSAccess;


use Curator\FSAccess\PathParser\PosixPathParser;

/**
 * Class StreamWrapperFtpAdapter
 *   ReadAdapterInterface and WriteAdapterInterface via stream-wrapped FTP.
 */
class StreamWrapperFtpAdapter implements ReadAdapterInterface, WriteAdapterInterface
{
  use ReadAdapterStreamWrapperTrait;
  use WriteAdapterStreamWrapperTrait;
  use CommonAdapterStreamWrapperTrait;

  /**
   * @var FtpConfigurationProviderInterface $ftp_config
   */
  protected $ftp_config;

  /**
   * @var StreamContextWrapper $context
   */
  protected $context;

  /**
   * @var string $wrapper_prefix
   *   Caches the stuff stuck to the front of paths for the ftp stream wrapper.
   */
  protected $wrapper_prefix = NULL;

  public function __construct(FtpConfigurationProviderInterface $ftp_config) {
    $this->ftp_config = $ftp_config;

    $this->context = new StreamContextWrapper(
      stream_context_create(
        array('ftp' =>
          array('overwrite' => TRUE)
        )
      ), 'ftp://'
    );
  }

  protected function alterPathForStreamWrapper($path) {
    if ($this->wrapper_prefix === NULL) {
      $prefix = 'ftp://';
      if ($this->ftp_config->getUsername() !== NULL) {
        $prefix .= sprintf('%s:%s@',
            $this->ftp_config->getUsername(),
            $this->ftp_config->getPassword()
        );
      }
      $prefix .= $this->ftp_config->getHostname();
      if ($this->ftp_config->getPort()) {
        $prefix .= ':' . $this->ftp_config->getPort();
      }
      $this->wrapper_prefix = $prefix;
    }

    if (strncmp('/', $path, 1) !== 0) {
      throw new \RuntimeException('Relative paths are not supported.');
    }
    return $this->wrapper_prefix . $path;
  }

  public function getPathParser() {
    // FTP path rules conform well enough to posix
    return new PosixPathParser();
  }

  public function getAdapterName() {
    return 'FTP stream wrapper';
  }

  public function getStreamContext() {
    return $this->context;
  }

  /**
   * @inheritdoc
   *
   * @return string
   *   Always returns the empty string: stream wrappers do not support pwd().
   */
  public function getCwd() {
    return '';
  }
}
