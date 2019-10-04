<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;

use Kaecyra\ChatBot\Bot\Command\CommandRouter;
use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;
use Psr\Log\LogLevel;

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
     * @param CommandRouter $router
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_presence_change(CommandRouter $router, Roster $roster, MessageInterface $message) {
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
        $router->onPresenceChange($user);
    }

}