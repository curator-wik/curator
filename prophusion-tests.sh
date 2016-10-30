#!/bin/bash
# This script is not intended for direct manual execution;
# It runs within the docker test runner container.
#
# To run Curator's full test suite, run run-tests.sh instead.

cd /curator
echo "Running PHPUnit tests..."
if [ -z "$PHPUNIT" ]
then
  PHPUNIT="./vendor/bin/phpunit"
  export PHPUNIT
fi

$PHPUNIT
