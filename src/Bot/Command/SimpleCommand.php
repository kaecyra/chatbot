<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

/**
 * Simple command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SimpleCommand extends AbstractCommand {

    public function __construct(string $method) {
        parent::__construct();
        $this->setCommand($method);
    }

    /**
     * Check if command is ready to run
     *
     * @return bool
     */
    public function isReady(): bool {
        return true;
    }

}