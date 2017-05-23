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
     * Command supplemental data
     * @var array
     */
    protected $data;

    /**
     * Execute method
     * @var string
     */
    protected $command;


    public function __construct() {
        $this->setCommand('');
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


}