<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;
use Kaecyra\ChatBot\Bot\BotUser;
use Kaecyra\ChatBot\Bot\Command\CommandRouter;
use Kaecyra\ChatBot\Bot\Conversation;
use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;

/**
 * Messages protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Messages extends AbstractProtocolHandler {

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {
        $client->addMessageHandler('message', [$this, 'message_message']);
        $client->addMessageHandler('user_typing', [$this, 'message_user_typing']);
    }

    /**
     * Receive communication messages (u2u, u2c)
     *
     * @param CommandRouter $router
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_message(CommandRouter $router, Roster $roster, BotUser $bot, MessageInterface $message) {
        $subtype = $message->get('subtype');
        if ($subtype) {
            return;
        }

        $userObject = $roster->getUser('id', $message->get('user'));
        $channelID = $message->get('channel');
        if ($userObject->getID() == $bot->getID()) {
            return;
        }

        $messageType = substr($channelID, 0, 1);
        switch ($messageType) {
            case 'C':
            case 'G':
                // Channel message
                $roomObject = $roster->getRoom('id', $channelID);
                $router->onGroupMessage($roomObject, $userObject, $message->get('text'));
                break;

            case 'D':
                // Direct message

                // Ingest conversation if missing
                try {
                    $conversationObject = $roster->getConversation('id', $channelID);
                } catch (\Kaecyra\ChatBot\Bot\Map\MapNotFoundException $ex) {
                    $conversationObject = new Conversation($channelID, $userObject);
                    $roster->map($conversationObject);
                }

                $router->onDirectMessage($userObject, $message->get('text'));
                break;
        }
    }

    /**
     * Receives user_typing messages
     *
     * @param CommandRouter $router
     * @param Roster $roster
     * @param BotUser $bot
     * @param MessageInterface $message
     */
    public function message_user_typing(CommandRouter $router, Roster $roster, BotUser $bot, MessageInterface $message) {
        $userObject = $roster->getUser('id', $message->get('user'));

        $channelID = $message->get('channel');
        if ($userObject->getID() == $bot->getID()) {
            return;
        }

        $messageType = substr($channelID, 0, 1);
        switch ($messageType) {
            case 'C':
            case 'G':
                // Channel message
                $conversationObject = $roster->getRoom('id', $channelID);
                break;

            case 'D':
                // Direct message
                // Ingest conversation if missing
                try {
                    $conversationObject = $roster->getConversation('id', $channelID);
                } catch (MapNotFoundException $ex) {
                    $conversationObject = new Conversation($channelID, $userObject);
                    $roster->map($conversationObject);
                }
                break;
        }

        // Fire "userTyping" event
        $this->fire('userTyping', [
            $conversationObject,
            $userObject,
            $message
        ]);
    }
}
