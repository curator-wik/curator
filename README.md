[![Build Status](https://travis-ci.org/curator-wik/curator.svg?branch=master)](https://travis-ci.org/curator-wik/curator)
# What's this?

Early-stage development of the server-side components for the Curator web install toolkit.

## Usage

If you only want to use Curator, and do not mean to contribute to its development, you 
should use a release build .phar file. There aren't any official releases yet.

### Hosting the source tree in Docker:
For a good developer experience, Curator can be hosted directly from its source tree
rather than out of a .phar.
The recommended way to do this is to install Docker and docker-compose if necessary, 
and run the `docker-host_app.sh` script. An Apache server hosting the application will
begin listening on localhost:8080.

**This repository only produces a RESTful API** because the server side is "headless"
and [curator's gui](https://github.com/curator-wik/curator-gui) is an AngularJS 
single-page web application. You can quickly test that the API is up and running by
checking for any JSON response from 
[http://localhost:8080/dist/web/curator.php/api/v1/status](http://localhost:8080/dist/web/curator.php/api/v1/status).

When developing, you should debug and validate your changes primarily by writing tests, versus
by manually observing them in the UI.

It is possible to point [curator-gui](https://github.com/curator-wik/curator-gui)
at your development server's API endpoint; see [curator-gui](https://github.com/curator-wik/curator-gui)'s
README.md for details.
<!--
 1. `cp dist/web/index.php dist/web/curator.php` -- the application won't launch itself unless it's
    named curator.php. This enables Curator to exist under a webserver's public directory tree under 
    another name (e.g. `drupal-curator.php`) and only be invoked if the application it supports wants 
    to `include` and allow a particular user to run it.
 2. If you plan to use the gui, symlink or copy the `build` folder from curator-gui as described above.
 2. Hit /dist/web/curator.php in your browser.
-->

### Running the tests
Curator has environment-independent unit tests, as well as integration tests that require
a particular OS, filesystem, and services. It is easy to run both types locally. 

You can quickly run the unit tests against the version of PHP on your computer by
installing PHPUnit and running ` phpunit --testsuite 'Curator Unit Tests'` from the
repository root. The repository also includes testing configuration files for
PhpStorm, but YMMV.

For the integration tests, Curator makes use of docker and the [prophusion](https://prophusion.org/)
base image to create a reproducible test environment where only the PHP version varies. 
To run all tests against all versions of PHP that Curator supports, install docker and
docker-compose if necessary and run `docker-all_tests.sh`.

There is no magic to the CI testing applied to pull requests that you cannot 
readily access on your local development system. The CI will pass if `docker-all_tests.sh` does.

### Building a .phar:
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
 
## License
MIT

Copyright &copy; 2017 Mike Baynton
