#!/bin/bash
# This script is not intended for direct manual execution;
# It runs within the docker test runner container.
#
# To run Curator's full test suite, run docker-all_tests.sh instead.

if [[ $(whoami) == "root" ]]; then
  su --preserve-env --login www-data $0
  exit $?
fi

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

# echo "XDEBUG2HOST="${XDEBUG2HOST}"; . /usr/local/bin/xdebug2host; cd /curator; /usr/local/phpenv/shims/php $PHPUNIT" | sudo -u www-data --preserve-env --login
cd /curator
/usr/local/phpenv/shims/php $PHPUNIT
