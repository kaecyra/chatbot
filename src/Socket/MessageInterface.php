<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Socket;

/**
 * Socket message interface
 *
 * This class parses and encodes messages on the wire.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface MessageInterface {


    /**
     * Parse wire format of message
     *
     * @param string $message
     */
    public function parse(string $message): MessageInterface;

    /**
     * Return wire format of message
     *
     * @return string
     */
    public function compile(): string;

    /**
     * Populate message object
     *
     * @param array $messageData
     */
    public function populate(string $method, array $data): MessageInterface;

    /**
     * Get message method
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Set message method
     *
     * @param string $method
     * @return SocketMessage
     */
    public function setMethod(string $method): MessageInterface;

    /**
     * Get message data
     *
     * @return mixed
     */
    public function getData(): array;

    /**
     * Set message data
     *
     * @param array $data
     * @return SocketMessage
     */
    public function setData(array $data): MessageInterface;

    /**
     * Convenience string function
     *
     * @return string
     */
    public function __toString();
}