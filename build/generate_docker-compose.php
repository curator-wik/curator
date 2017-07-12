<?php
/**
 * @file Makes test runs adjustable by generating a file for docker-compose.
 */
require __DIR__ . "/Spyc.php";
$template = Spyc::YAMLLoad(__DIR__ . '/docker-compose-template.yml');

$opts = getopt('', ['coverage']);

if (array_key_exists('coverage', $opts)) {
  $template['services']['phpunit_runner_7_1']['environment'][] = 'PHPUNIT_COVERAGE=--coverage-clover /host_tmp/curator-clover.xml';
}

$footer = <<<FOOTER

volumes:
  php-builds: {}

FOOTER;

$generated = Spyc::YAMLDump($template) . $footer;

file_put_contents(__DIR__ . '/../.docker-compose_generated.yml', $generated);
