<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;

use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;

use Kaecyra\ChatBot\Bot\Persona;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;

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
     * @param Persona $persona
     * @param Roster $roster
     * @param MessageInterface $message
     * @return type
     */
    public function message_presence_change(Persona $persona, Roster $roster, MessageInterface $message) {
        $uid = $message->get('user');

        try {
            $user = $roster->getUser('id', $uid);
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not update presence for user '{uid}'. {reason}", [
                'uid' => $uid,
                'reason' => $ex->getMessage()
            ]);
            return;
        }

        $presence = $message->get('presence');
        $old = $user->getPresence();
        $this->tLog(LogLevel::NOTICE, "{user} ({uid}) is now {presence} (was {old})", [
            'user' => $user->getName(),
            'uid' => $user->getID(),
            'old' => $old,
            'presence' => $presence
        ]);

        $user->setPresence($presence);
        $persona->onPresenceChange($user);
    }

}