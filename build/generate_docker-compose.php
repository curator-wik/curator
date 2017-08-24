<?php
/**
 * @file Makes test runs adjustable by generating a file for docker-compose.
 */
require __DIR__ . "/Spyc.php";
$template = Spyc::YAMLLoad(__DIR__ . '/docker-compose-template.yml');

$opts = getopt('', ['coverage', 'filter:', 'testsuite:']);

if (array_key_exists('coverage', $opts)) {
  $template['services']['phpunit_runner_7_1']['environment'][] = 'PHPUNIT_COVERAGE=--coverage-clover /host_tmp/curator-clover.xml';
}

$phpunit_args = [];
foreach (array_intersect(['filter', 'testsuite'], array_keys($opts)) as $test_selection) {
  // Yes, this offers potential for cli injection, but this is a non-release test script that requires a cli anyway.
  // Hack around the yaml generator's failure to escape backslashes
  $opts[$test_selection] = str_replace('\\', '\\\\', $opts[$test_selection]);
  $phpunit_args[] = "--$test_selection=" . escapeshellarg($opts[$test_selection]);
}

if (! empty($phpunit_args)) {
  foreach ($template['services'] as &$container) {
    $container['environment'][] = "PHPUNIT_EXTRA_ARGS=" . implode(' ', $phpunit_args);
  }
}

$footer = <<<FOOTER

volumes:
  php-builds: {}

FOOTER;

$generated = Spyc::YAMLDump($template) . $footer;

file_put_contents(__DIR__ . '/../.docker-compose_generated.yml', $generated);
