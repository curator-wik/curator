#!/usr/bin/env bash
docker-compose run -e "XDEBUG2HOST=serverName=docker-test-env.curatorwik" phpunit_runner_5_5
