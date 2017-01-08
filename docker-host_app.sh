#!/usr/bin/env bash
# This script helps launch the docker container used to run the Curator code tree in a full apache environment.
# HTTP server will appear on your docker host's loopback interface, port 8080.

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd "$SCRIPT_DIR"

# The application won't run standalone unless it's named curator.php. This feature allows Curator to exist in a
# webserver's public directory tree under another name (e.g. `drupal-curator.php`) and only be invoked if the
# application it supports wants to `include` and allow a particular user to run it. Doing the below tells Curator
# to enable standalone mode.
if [ ! -f "dist/web/curator.php" ]; then
  cp dist/web/index.php dist/web/curator.php
fi

docker-compose -f docker-compose-apache.yml up
