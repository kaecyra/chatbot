<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Socket\MessageInterface;

/**
 * Message object
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Message {

    /**
     *
     * @var MessageInterface
     */
    protected $socketMessage;

    /**
     * Set up message
     *
     * @param MessageInterface $message
     */
    public function __construct(
        MessageInterface $message
    ) {
        $this->socketMessage = $message;
    }

}