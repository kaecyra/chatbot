<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use Kaecyra\ChatBot\Socket\AbstractSocketMessage;
use Psr\Log\LogLevel;

use \Exception;

/**
 * Slack RTM socket message
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SocketMessage extends AbstractSocketMessage {

    /**
     * Parse JSON encoded wire formatted message
     *
     * @param string $message
     * @return MessageInterface
     * @throws Exception
     */
    public function parse(string $message): MessageInterface {
        $messageData = json_decode($message, true);
        if ($messageData === false) {
            throw new \Exception('Unable to decode incoming message');
        }

        $message = self::create();
        $message->populate($messageData);
        return $message;
    }

    /**
     * Return JSON encoded wire formatted message
     *
     * @return string
     */
    public function compile(): string {
        return json_encode([
            'method' => $this->method,
            'data' => $this->data
        ]);
    }

}