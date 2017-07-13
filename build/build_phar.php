<?php

$here = dirname(__FILE__);

// Are we running in the project root?
if (! is_dir('dist')) {
  fwrite(STDERR, "FATAL: dist/ directory not found. Run this script with the project root as the working directory.\n");
  exit(1);
}

echo "Deleting any existing phars kicking around from a past run\n";
foreach (array_map(function($n) use($here) { return "$here/$n";},
  ['curator.phar', 'curator.phar.gz', 'curator.phar.bz2', 'backdrop-curator.phar']) as $path) {
  if (file_exists($path)) {
    unlink($path) or die("Could not delete $path\n");
  }
}

echo "Creating distribution version of vendor/ directory...\n";
`composer install --no-dev --optimize-autoloader -d dist/`;

echo "Creating new .phar and setting stub...\n";
$p = new Phar(dirname(__FILE__) . '/curator.phar', 0, 'curator');
$p->setStub(file_get_contents($here . '/phar_stub.php'));

//$p->buildFromDirectory(dirname(__FILE__) . '/dist');
$iterator_chain = new PharFilter(
  new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($here . '/../dist')
  )
);

$p->buildFromIterator($iterator_chain, $here . '/../dist');

$p->compress(Phar::GZ);
$p->compress(Phar::BZ2);

// file_put_contents('backdrop-curator.phar', file_get_contents('curator.phar'));

class PharFilter extends FilterIterator {
  public function accept() {
    /**
     * @var SplFileInfo $file
     */
    $file = $this->getInnerIterator()->current();
    $basename = $file->getBasename();

    if ($basename == '.' || $basename == '..') {
      return FALSE;
    }

    $patterns = [
      '^README',
      '\.rst$',
      '\.md$',
      '/Tests/',
      '/Test/',
      '/tests/',
      'phpunit',
      'composer\.json$',
      'composer\.lock$',
      '^\.gitignore$',
      '^\.travis\.yml$',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match("|$pattern|", $file)) {
        return FALSE;
      }
    }

    return TRUE;
  }
}
