#!/usr/bin/env bash

# Generate a file for docker-compose...
DCFILE='.docker-compose_generated.yml'
/usr/bin/env php './build/generate_docker-compose.php' "$@"

docker-compose -f "$DCFILE" run -e "XDEBUG2HOST=serverName=docker-test-env.curatorwik" phpunit_runner_5_5

docker-compose -f "$DCFILE" rm -f phpunit_runner_5_5
