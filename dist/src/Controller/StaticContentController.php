<?php


namespace Curator\Controller;


use Curator\AppManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaticContentController {

  /**
   * @var AppManager $app_manager
   */
  protected $app_manager;

  public function __construct(AppManager $app_manager) {
    $this->app_manager = $app_manager;
  }

  public function generateSinglePageHost(Request $request) {
    $index_html = static::getGuiPath() . DIRECTORY_SEPARATOR . 'index.html';
    if (file_exists($index_html)) {
      $base = $request->getSchemeAndHttpHost() . ':' . $request->getPort() . $request->getBaseUrl() . '/';
      $markup = file_get_contents($index_html);
      return str_replace('<head>', "<head><base href=\"$base\" />", $markup);
    } else {
      throw new NotFoundHttpException();
    }
  }

  public function serveStaticFile(Request $request) {
    // See if the request matches a file that we have
    $root = static::getGuiPath();
    $presumed_path = sprintf('%s%s', $root, urldecode($request->getPathInfo()));
    if (is_file($presumed_path)) {
      return static::serveStaticFileAtPath($presumed_path);
    } else {
      return FALSE;
    }
  }

  public function serveStaticFileAtPath($path) {
    $headers = [
      'X-Status-Code' => 200
    ];
    $type = self::mapExtToMimeType($path);
    if ($type !== NULL) {
      $headers['Content-Type'] = $type;
    }
    return new BinaryFileResponse(
      new \SplFileInfo($path),
      200,
      $headers
    );
  }

  public function mapExtToMimeType($filename) {
    $map = [
      'js'    => 'text/javascript',
      'css'   => 'text/css',
      'html'  => 'text/html',
      'json'  => 'application/json',
    ];

    $ext = explode('.', basename($filename));
    if (count($ext) > 1) {
      $ext = array_pop($ext);
      if (array_key_exists($ext, $map)) {
        return $map[$ext];
      }
    }

    return NULL;
  }

  public function getGuiPath() {
    return $this->getWebPath() . "curator-gui";
  }

  public function getWebPath() {
    if ($this->app_manager->isPhar()) {
      $root = 'phar://curator/web/';
    } else {
      $root = '';
    }
    return $root;
  }
}
