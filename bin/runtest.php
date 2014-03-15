#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);
ini_set('display_errors', true);

function exception_error_handler($errno, $errstr, $errfile, $errline) {
    if (error_reporting() & $errno)
        throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");

$tests = new MustacheTest;
$tests->run();

