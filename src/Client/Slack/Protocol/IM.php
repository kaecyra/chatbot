<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;
use Kaecyra\ChatBot\Bot\Conversation;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;
use Psr\Log\LogLevel;

/**
 * IM protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class IM extends AbstractProtocolHandler {

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {
        $client->addMessageHandler('im_created', [$this, 'message_im_created']);
    }

    /**
     * Handle im creation
     *
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_im_created(Roster $roster, MessageInterface $message) {
        $userObject = $roster->getUser('id', $message->get('user'));
        $conversationObject = new Conversation($message->get('channel.id'), $userObject);
        $roster->map($conversationObject);
        $this->tLog(LogLevel::INFO, "IM {imid} created with {name} ({uid})", [
            'imid' => $conversationObject->getID(),
            'name' => $userObject->getReal(),
            'uid' => $userObject->getID()
        ]);
    }

}