<?php
/**
 * @file
 * Executed by PHPUnit before running tests, per phpunit.xml.dist.
 */
require 'vendor/autoload.php';

// Engage conversion of errors to ErrorExceptions.
\Symfony\Component\Debug\ErrorHandler::register();

echo sprintf("PHP_IDE_CONFIG=%s\n", getenv('PHP_IDE_CONFIG'));
