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
class CommandResponse {

    const RESPONSE_OK = 'OK';
    const RESPONSE_ERROR = 'ERROR';
    const RESPONSE_NO_HANDLER = 'NO_HANDLER';
    const RESPONSE_REQUEUE = 'REQUEUE';
    const RESPONSE_EXPIRED = 'EXPIRED';

    /**
     * Command response
     * @var string
     */
    protected $response;

    /**
     * Command requeue delay (seconds)
     * @var int
     */
    protected $delay;

    /**
     * Treat delay as delta vs absolute
     * @var bool
     */
    protected $delta;

    public function __construct(string $response = self::RESPONSE_OK) {
        $this->response = $response;
        $this->delay = 0;
        $this->delta = true;
    }

    /**
     * Get response
     *
     * @return string
     */
    public function getResponse(): string {
        return $this->response;
    }

    /**
     * Set response
     *
     * @param mixed $response
     * @return CommandResponse
     */
    public function setResponse($response): CommandResponse {
        $this->response = $response;
        return $this;
    }

    /**
     * @return int
     */
    public function getDelay(): int {
        return $this->delay;
    }

    /**
     * @param int $delay
     * @return CommandResponse
     */
    public function setDelay(int $delay): CommandResponse {
        $this->delay = $delay;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDelta(): bool {
        return $this->delta;
    }

    /**
     * @param bool $delta
     * @return CommandResponse
     */
    public function setDelta(bool $delta): CommandResponse {
        $this->delta = $delta;
        return $this;
    }

}