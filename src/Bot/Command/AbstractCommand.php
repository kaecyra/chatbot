<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

/**
 * Abstract Command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractCommand implements CommandInterface, \ArrayAccess {

    /**
     * Command create time
     * @var int
     */
    protected $createTime;

    /**
     * Command supplemental data
     * @var array
     */
    protected $data;

    /**
     * Execute method
     * @var string
     */
    protected $command;

    /**
     * Expiry delta
     * @var int
     */
    protected $expiry;

    public function __construct() {
        $this->setCommand('');
        $this->createTime = time();
        $this->expiry = 0;
        $this->data = [];
    }

    /**
     * Get method
     *
     */
    public function getCommand(): string {
        return $this->command;
    }

    /**
     * Set command
     *
     * @param string $command
     * @return CommandInterface
     */
    public function setCommand(string $command): CommandInterface {
        $this->command = $command;
        return $this;
    }

    /**
     * Test whether we have a command
     * 
     * @return bool
     */
    public function haveCommand(): bool {
        return !empty($this->command) && strlen($this->command);
    }

    /**
     * Get token/piece by index
     *
     * @param integer $index
     * @return string|null
     */
    public function index($index) {
        return $this->data['pieces'][$index] ?? null;
    }

    /**
     * Get data by key
     *
     * @param string $key
     */
    public function &__get ($key) {
        return $this->data[$key];
    }

    /**
     * Assigns value by key
     *
     * @param string $key
     * @param mixed $value value to set
     */
    public function __set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Whether or not data exists by key
     *
     * @param string $key to check for
     * @return boolean
     */
    public function __isset($key) {
        return isset($this->data[$key]);
    }

    /**
     * Unset data by key
     *
     * @param string $key
     */
    public function __unset($key) {
        unset($this->data[$key]);
    }

    /**
     * Check if offset exists
     *
     * @param mixed $offset
     */
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    /**
     * Set value on offset
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Get offset value
     *
     * @param mixed $offset
     */
    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Unset offset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    /**
     * Set expiry
     *
     * @param int $delta
     * @return $this
     */
    public function setExpiry(int $delta) {
        $this->expiry = $delta;
        return $this;
    }

    /**
     * Get expiry
     *
     * @return int
     */
    public function getExpiry(): int {
        return $this->expiry;
    }

    /**
     * Check expiry
     *
     * @return bool
     */
    public function isExpired(): bool {
        return $this->expiry ? (($this->expiry + $this->createTime) < time()) : false;
    }


}