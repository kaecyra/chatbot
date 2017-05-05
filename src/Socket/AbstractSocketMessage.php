<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Socket;

use Kaecyra\ChatBot\Socket\MessageInterface;

/**
 * Abstract socket message
 *
 * This class parses and encodes messages on the wire.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractSocketMessage implements MessageInterface {

    /**
     *
     * @var string
     */
    protected $method;

    /**
     *
     * @var array
     */
    protected $data;

    /**
     * Parse wire format of message
     *
     * @param string $message
     */
    abstract public function parse(string $message): MessageInterface;

    /**
     * Return wire format of message
     *
     * @return string
     */
    abstract public function compile(): string;

    /**
     * Populate message object
     *
     * @param string $method
     * @param array $data
     * @return MessageInterface
     */
    public function populate(string $method, array $data): MessageInterface {
        $this->method = $method;
        $this->data = $data;
        return $this;
    }

    /**
     * Get message method
     *
     * @return string
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * Set message method
     *
     * @param string $method
     * @return SocketMessage
     */
    public function setMethod(string $method): MessageInterface {
        $this->method = $method;
        return $this;
    }

    /**
     * Get message data
     *
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Set message data
     *
     * @param array $data
     * @return SocketMessage
     */
    public function setData(array $data): MessageInterface {
        $this->data = $data;
        return $this;
    }

    /**
     * Convenience string function
     *
     * @return string
     */
    public function __toString() {
        return $this->compile();
    }

}