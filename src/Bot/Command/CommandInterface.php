<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

/**
 * Command interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface CommandInterface {

    /**
     * Get command GUID
     * @return string
     */
    public function getGuid(): string;

    public function getCommand(): string;

    public function setCommand(string $command): CommandInterface;

    public function isExpired(): bool;

    public function setReady(bool $ready): CommandInterface;

    public function isReady(): bool;

    public function setAwaitConfirmation(bool $wait = true): CommandInterface;

    public function isWaiting(): bool;

}