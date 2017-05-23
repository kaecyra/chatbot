<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use Kaecyra\ChatBot\Socket\AbstractSocketMessage;
use Kaecyra\ChatBot\Socket\MessageInterface;

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
    public function ingest(string $message): MessageInterface {
        $messageData = json_decode(trim($message), true);
        if (!is_array($messageData)) {
            throw new \Exception('Unable to decode incoming message');
        }

        $method = $messageData['type'];
        unset($messageData['type']);

        $this->populate($method, $messageData);
        return $this;
    }

    /**
     * Return JSON encoded wire formatted message
     *
     * @return string
     */
    public function compile(): string {
        return json_encode(array_merge([
            'type' => $this->method
        ], $this->data));
    }

}