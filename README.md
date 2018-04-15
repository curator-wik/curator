[![Build Status](https://travis-ci.org/curator-wik/curator.svg?branch=master)](https://travis-ci.org/curator-wik/curator)
# What's this?

Server-side components for the Curator web install toolkit.

The broad goal is to create a project for updating and installing PHP web applications 
through an in-browser UI that is **reusable** and can optionally be **self-sufficient**.
 * **reusable** as in by different PHP web applications. The problem space of securely affecting
   updates to a web application through an in-browser and/or automatic modality includes 
   nontrivial challenges that are not materially different from application to application,
   so it should be possible to implement the best solutions to those challenges once as a
   library, rather than asking each application to solve them from scratch.
 * **self-sufficient** as in capable of operating without relying on any facilities of the application
   being updated while the update itself is taking place. This provides important support
   for traditional PHP applications that lack the concept of a ["bootloader,"](https://youtu.be/wmhD6lA3PRs?t=24m50s)
   or capability to route requests to an "A" or "B" version of the source tree by sidestepping complications
   that could arise from updating code while it's being used to perform the update and
   by providing assurance that the updater will remain functional even
   if the update itself fails badly.
   
   In practice, self-sufficiency means the project's build system should offer the option of
   creating a .phar that includes all necessary frontend & backend code and assets to allow a user 
   to run an update via their web browser.

   Because additional benefits and functionality are only possible when applications more
   closely coordinate with the updater (atomic transitions from old to new version, tighter
   UI integrations,) library-only releases of the project that do not include UI or AppKernel
   of their own should also be produced.

## Status of development

As of April 2018, the problems solved in developed & tested code are:
  * A format for expressing and packaging the deltas between two or more releases of a source tree ([docs](https://github.com/curator-wik/common-docs/blob/master/update_package_structure.md)).
    This may be useful for applications that primarily ship new releases as e.g. `tar`
    archives (as opposed Composer files.) The idea is that a one-time process examines two such
    official release tarballs and creates a package expressing the deltas. This preprocessing
    allows the work performed by the updater client application to be significantly reduced.
  * A batch framework for PHP that supports concurrency when the set of tasks to be run are
    trivially parallelizable ([repo](https://github.com/mbaynton/batch-framework)).
  * A filesystem access abstraction layer whose differentiator from the many other such
    libraries is its ability to transparently direct I/O through different backend adapters depending
    on whether the operation is a read or a write. ([`Curator\FSAccess` namespace](https://github.com/curator-wik/curator/tree/master/dist/src/FSAccess)).  
    This is a performance optimization allowing the updater to read the current state of files via
    the mounted filesystem even when writes must be performed through a slower method like FTP.
  * Code that downloads packaged deltas and translates the download to a batch job that applies it
    on a source tree via the filesystem abstraction layer.
    ([`BatchTaskTranslationService` &](https://github.com/curator-wik/curator/blob/master/dist/src/Cpkg/BatchTaskTranslationService.php)
    friends in the [`Curator\Cpkg` namespace](https://github.com/curator-wik/curator/tree/master/dist/src/Cpkg)).

Some nearly-complete / experimental features are:
  * Support for "lightweight" application integrations ([docs](https://github.com/curator-wik/curator/blob/master/docs/Integration-HOWTO.md)).
    Lightweight integrations use the build of Curator that exhibits self-sufficiency and simply allow
    user authz and update task configuration to flow from the target application to the updater. They
    can be implemented as simple modules / plugins / extensions to the target application.
  * Application of patch files to possibly modified original source files with no requirement for
    external utilities. (Patches do not always apply correctly.) The goal here would be to offer
    tooling to deploy small but highly critical patches even to sites with user modified sources.

Incomplete features are:
  * [The UI](https://github.com/curator-wik/curator-gui).
  * Any kind of support for cryptographic verification of new code before it is installed. The project
    currently runs on PHP all the way back to 5.4 in the interests of providing a low bar for as many
    users as possible to receive updates<sup>*</sup>, and sodium-compat was not yet a thing.
  * Awareness of the Composer ecosystem.
    
<sup>*</sup> The 5.4 decision was made 2 years ago when 5.4 was much more predominant on
[this graph](https://wordpress.org/about/stats/), so it could be bumped somewhat if there was a compelling
reason.

### Development getting started:
For a good developer experience, Curator can be hosted directly from its source tree
rather than out of a .phar.
The recommended way to do this is to install Docker and docker-compose if necessary, 
and run the `docker-host_app.sh` script. An Apache server hosting the application will
begin listening on localhost:8080.

**This repository only produces some APIs** because the server side is "headless."
[Curator's UI](https://github.com/curator-wik/curator-gui) is an AngularJS 
single-page web application. You can quickly test that the API is up and running by
checking for any JSON response from 
[http://localhost:8080/dist/web/curator.php/api/v1/status](http://localhost:8080/dist/web/curator.php/api/v1/status).

When developing, you ought to debug and validate your changes by writing tests versus by manually observing them 
in the UI. However, it is possible to point [curator-gui](https://github.com/curator-wik/curator-gui)
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
First, install dependencies via composer:
```bash
composer install && composer install --no-dev --optimize-autoloader -d dist/
```

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
 3. Run `php build/build_phar.php` in the project root. A number of .phar, .phar.gz and .phar.bz2 files
    will be produced in the build directory.
 4. Your server likely is not configured to run .phar archives directly from web requests, but
    you can try hitting /curator.phar in your browser. If that doesn't work, hit the script /curator.php
    which basically `include`s the phar and launches the application it contains.

## System Requirements
 * PHP 5.4.0 or newer on a Linux operating system. Windows is not officially supported at
   this time.
 * Ability for the webserver to write to the system's temporary file location (for 
   temporary storage of update packages.)
 * Ability to write in the application's document root, via either FTP or mounted
   filesystem.

Your installation of PHP must also support extraction of either .zip or .tar format
archive files. Zip is marginally preferable as it is an indexed format offering better 
random access performance.
 
## License
MIT

Copyright &copy; 2017 Mike Baynton
