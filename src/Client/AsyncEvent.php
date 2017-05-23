<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;

/**
 * Async event wrapper
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class AsyncEvent {

    /**
     * Creation time
     * @var int
     */
    protected $created;

    /**
     * Execution time
     * @var int
     */
    protected $execute;

    /**
     *
     * @var int
     */
    protected $queued;

    /**
     *
     * @var CommandInterface
     */
    protected $command;

    /**
     * Constructor
     *
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command) {
        $this->created = time();
        $this->queued = 0;
        $this->command = $command;
    }

    /**
     * Set future execution time
     *
     * @param int $executeAt
     * @param bool $delta
     * @return AsyncEvent
     */
    public function setExecute(int $executeAt, bool $delta): AsyncEvent {
        $this->execute = $delta ? time()+$executeAt : $executeAt;
        return $this;
    }

    /**
     * Get execution time
     *
     * @return int
     */
    public function getExecute(): int {
        return $this->execute;
    }

    /**
     * Can execution occur?
     *
     * @return bool
     */
    public function canExecute(): bool {
        return ($this->execute <= time());
    }

    /**
     * Get created time
     *
     * @return int
     */
    public function getCreated(): int {
        return $this->created;
    }

    /**
     * Increment queued counter
     *
     * @return AsyncEvent
     */
    public function wasQueued(): AsyncEvent {
        $this->queued++;
        return $this;
    }

    /**
     * Get queued counter
     *
     * @return int
     */
    public function getQueued(): int {
        return $this->queued;
    }

    /**
     * Reset queued to zero
     *
     * @return AsyncEvent
     */
    public function resetQueued(): AsyncEvent {
        $this->queued = 0;
        return $this;
    }

    /**
     * Set async command to execute
     *
     * @param CommandInterface $command
     * @return AsyncEvent
     */
    public function setCommand(CommandInterface $command): AsyncEvent {
        $this->command = $command;
        return $this;
    }

    /**
     * Get async command
     *
     * @return CommandInterface
     */
    public function getCommand(): CommandInterface {
        return $this->command;
    }

}