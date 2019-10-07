<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Error;

use Garden\Daemon\ErrorHandlerInterface;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Log error handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class LogErrorHandler implements ErrorHandlerInterface, LoggerAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;

    /**
     * Log error
     *
     * @param int $errorLevel
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     */
    public function error($errorLevel, $message, $file, $line, $context) {
        $errorFormat = "PHP {levelString}: {message} in {file} on line {line}";

        $level = $this->phpErrorLevel($errorLevel);
        $this->log($level, $errorFormat, [
            'level' => $level,
            'levelString' => ucfirst($level),
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]);
    }

}