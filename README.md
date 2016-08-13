[![Build Status](https://travis-ci.org/curator-wik/curator.svg?branch=master)](https://travis-ci.org/curator-wik/curator)
# What's this?

Early-stage development of the server-side components for the Curator web install toolkit.

## Usage
### From a .phar:
Curator's primary distribution will be a single php archive (.phar) file containing the server instructions
and [curator-gui](https://github.com/curator-wik/curator-gui). To build a .phar of both projects,
 1. Create a production build of curator-gui: `user@pc:~/curator-gui$ foundation build`
 2. Copy the build folder from the curator-gui project repo to a directory named curator-gui under
    dist/web. So, the file `curator/dist/web/curator-gui/index.html` should exist as a result.
 3. Run `php ./build_phar.php` in the project root. A number of .phar, .phar.gz and .phar.bz2 files
    will be produced at the project root.
 4. Your server likely is not configured to run .phar archives directly from web requests, but
    you can try hitting /curator.phar in your browser. If that doesn't work, hit the script /curator.php
    which basically `include`s the phar and launches the application it contains.

### From source:
For development, Curator will also run unpackaged directly from its source tree. To do this,
 1. `cp dist/web/index.php dist/web/curator.php` -- the application won't launch itself unless it's
    named curator.php. This enables Curator to exist under a webserver's public directory tree under 
    another name (e.g. `drupal-curator.php`) and only be invoked if the application it supports wants 
    to `include` and allow a particular user to run it.
 2. If you plan to use the gui, symlink or copy the `build` folder from curator-gui as described above.
 2. Hit /dist/web/curator.php in your browser.
 
 ## License
 The major components of the curator-wik project will likely be licensed under the MIT
 license, but license selection is ongoing. Make no licensing assumptions about this work
 until further notice.
