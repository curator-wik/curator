#!/bin/bash
# This script is not intended for direct manual execution;
# It runs within the docker test runner container.
#
# To run Curator's full test suite, run docker-all_tests.sh instead.

cd /curator
echo "Running PHPUnit tests..."
if [ -z "$PHPUNIT" ]
then
  PHPUNIT="./vendor/bin/phpunit"
fi


if [[ "$PHPUNIT_COVERAGE" != "" ]]
then
  PHPUNIT="$PHPUNIT $PHPUNIT_COVERAGE"
fi

if [[ "$PHPUNIT_EXTRA_ARGS" != "" ]]
then
  PHPUNIT="$PHPUNIT $PHPUNIT_EXTRA_ARGS"
fi

echo "PHPUNIT is: $PHPUNIT"
export PHPUNIT

$PHPUNIT
