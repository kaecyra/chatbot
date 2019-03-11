<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

/**
 * Command interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface CommandInterface {

    public function getCommand(): string;

    public function setCommand(string $command): CommandInterface;

    public function isExpired(): bool;

    public function isReady(): bool;

}