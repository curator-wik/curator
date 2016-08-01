<?php


namespace Curator\Controller;


use Curator\AppManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SinglePageHostController {

  public static function generateSinglePageHost(Request $request) {
    /*
    $template = <<<TPL
<!doctype html>
<html ng-app="application">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Curator</title>
    <base href="%s" />
    <link href="assets/css/app.css" rel="stylesheet" type="text/css">
    <script src="assets/js/app.js"></script>
    <script src="assets/js/routes.js"></script>
    <script src="assets/js/angular.js"></script>
  </head>

  <style>
    .menu-bar {margin-bottom: 0;}
    #sub-nav, .messages {border-right: 1px solid #eee;}
    .medium-grid-content {padding:1rem !important;}
  </style>

  <body>
    <div class="grid-frame vertical">
      <div class="primary title-bar">
        <div class="center title"><a ui-sref="home">zMail</a></div>
        <span class="right"><a ui-sref="settings">Settings</a></span>
      </div>
      <div ui-view class="grid-block">

      </div>
    </div>
  </body>
</html>

TPL;

    return sprintf($template, $request->getSchemeAndHttpHost() . ':' . $request->getPort() . $request->getBaseUrl() . '/');
    */

    $index_html = static::getGuiPath() . DIRECTORY_SEPARATOR . 'index.html';
    if (file_exists($index_html)) {
      $base = $request->getSchemeAndHttpHost() . ':' . $request->getPort() . $request->getBaseUrl() . '/';
      $markup = file_get_contents($index_html);
      return str_replace('<head>', "<head><base href=\"$base\" />", $markup);
    } else {
      throw new NotFoundHttpException();
    }
  }

  public static function serveStaticFile(Request $request) {
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

  public static function mapExtToMimeType($filename) {
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

  protected static function getGuiPath() {
    $app_manager = AppManager::singleton();

    if ($app_manager->isPhar()) {
      $root = 'phar://curator.phar/web/curator-gui';
    } else {
      $root = 'curator-gui';
    }
    return $root;
  }
}
