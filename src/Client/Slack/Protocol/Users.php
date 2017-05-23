<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;

use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;

use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Bot\User;

use Psr\Log\LogLevel;

use \Exception;

/**
 * Users protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Users extends AbstractProtocolHandler {

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {
        $client->addMessageHandler('presence_change', [$this, 'message_presence_change']);
    }

    /**
     * Handle presence changes
     *
     * @param SlackRtmClient $client
     * @param MessageInterface $message
     */
    public function message_presence_change(SlackRtmClient $client, Roster $roster, MessageInterface $message) {
        $this->tLog(LogLevel::NOTICE, "Server sent 'presence_change'.");
        
    }

}