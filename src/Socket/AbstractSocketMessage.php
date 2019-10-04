<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Socket;

use Kaecyra\AppCommon\Store;

/**
 * Abstract socket message
 *
 * This class parses and encodes messages on the wire.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractSocketMessage extends Store implements MessageInterface {

    /**
     *
     * @var string
     */
    protected $method;

    /**
     * Ingest string message in wire format
     *
     * @param string $message
     */
    abstract public function ingest(string $message): MessageInterface;

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
        $this->prepare($data);
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
        return $this->dump();
    }

    /**
     * Set message data
     *
     * @param array $data
     * @return SocketMessage
     */
    public function setData(array $data): MessageInterface {
        $this->prepare($data);
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