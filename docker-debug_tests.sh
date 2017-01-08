#!/usr/bin/env bash
docker-compose run -e 'DOCKER_REMOTE_XDEBUG=1' phpunit_runner_5_5
