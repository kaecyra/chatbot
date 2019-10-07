#!/usr/bin/env php
<?php

/**
 * General purpose extensible PHP chat bot for Instant Messaging rooms.
 *
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 * @author Tim Gunter <tim@vanillaforums.com>
 */

namespace Kaecyra\ChatBot;

use Garden\Container\Container;
use Garden\Daemon\Daemon;
use Psr\Log\LogLevel;

// Switch to root directory
chdir(dirname($argv[0]));

// Include the core autoloader.

$paths = [
    getcwd().'/vendor/autoload.php',
    __DIR__.'/../vendor/autoload.php',  // locally
    __DIR__.'/../../../autoload.php',   // dependency
];
foreach ($paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Run bootstrap
$container = new Container;
ChatBot::bootstrap($container, [
    'appdescription'    => 'ChatBot',
    'appnamespace'      => 'Kaecyra\\ChatBot',
    'appname'           => 'ChatBot',
    'authorname'        => 'Tim Gunter',
    'authoremail'       => 'tim@vanillaforums.com'
]);

$exitCode = 0;
try {

    $daemon = $container->get(Daemon::class);
    $exitCode = $daemon->attach($argv);

} catch (\Garden\Daemon\Exception $ex) {

    $exceptionCode = $ex->getCode();
    if ($exceptionCode != 200) {

        if ($ex->getFile()) {
            $line = $ex->getLine();
            $file = $ex->getFile();
            $logger->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
        }
        $logger->log(LogLevel::ERROR, $ex->getMessage());

        foreach ($ex->getTrace() as $traceLine) {
            $logger->log(LogLevel::DEBUG, $traceLine);
        }
    }

} catch (\Exception $ex) {
    $exitCode = 1;

    if ($ex->getFile()) {
        $line = $ex->getLine();
        $file = $ex->getFile();
        $logger->log(LogLevel::ERROR, "Error on line {$line} of {$file}:");
    }
    $logger->log(LogLevel::ERROR, $ex->getMessage());

    foreach (explode("\n", $ex->getTraceAsString()) as $traceLine) {
        $logger->log(LogLevel::DEBUG, $traceLine);
    }
}

exit($exitCode);
