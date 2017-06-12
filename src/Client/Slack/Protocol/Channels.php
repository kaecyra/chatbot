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
use Kaecyra\ChatBot\Bot\Room;
use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Bot\BotUser;

use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;

use Psr\Log\LogLevel;

use \Exception;

/**
 * Channels protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Channels extends AbstractProtocolHandler {

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {
        $client->addMessageHandler('channel_joined', [$this, 'message_channel_joined']);
        $client->addMessageHandler('channel_left', [$this, 'message_channel_left']);

        $client->addMessageHandler('member_joined_channel', [$this, 'message_member_joined_channel']);
        $client->addMessageHandler('member_left_channel', [$this, 'message_member_left_channel']);

        $client->addMessageHandler('channel_created', [$this, 'message_channel_created']);
        $client->addMessageHandler('channel_deleted', [$this, 'message_channel_deleted']);
        $client->addMessageHandler('channel_rename', [$this, 'message_channel_rename']);

        $client->addMessageHandler('message:channel_topic', [$this, 'message_channel_topic']);
    }

    /**
     * Handle bot joins
     *
     * @param Roster $roster
     * @param BotUser $user
     * @param MessageInterface $message
     * @return type
     */
    public function message_channel_joined(Persona $persona, Roster $roster, BotUser $user, MessageInterface $message) {
        try {
            $roomObject = $roster->getRoom('id', $message->get('channel.id'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel self join event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $this->channelJoin($persona, $roomObject, $user);
    }

    /**
     * Handle bot leaves
     *
     * @param Roster $roster
     * @param BotUser $user
     * @param MessageInterface $message
     * @return type
     */
    public function message_channel_left(Persona $persona, Roster $roster, BotUser $user, MessageInterface $message) {
        try {
            $roomObject = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel self leave event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $this->channelLeave($persona, $roomObject, $user);
    }

    /**
     * Handle joins
     *
     * @param Roster $roster
     * @param MessageInterface $message
     * @return type
     */
    public function message_member_joined_channel(Persona $persona, Roster $roster, MessageInterface $message) {
        $reason = "";
        try {
            $userObject = $roster->getUser('id', $message->get('user'));
            $roomObject = $roster->getRoom('id', $message->get('channel'));
            if ($message->has('inviter')) {
                $inviter = $roster->getUser('id', $message->get('inviter'));
                $reason = "Invited by ".$inviter->getReal()." (".$inviter->getID().").";
            }
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel join event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }

        $this->channelJoin($persona, $roomObject, $userObject, $reason);
    }

    /**
     * Handle leaves
     *
     * @param Roster $roster
     * @param MessageInterface $message
     * @return type
     */
    public function message_member_left_channel(Persona $persona, Roster $roster, MessageInterface $message) {
        try {
            $userObject = $roster->getUser('id', $message->get('user'));
            $roomObject = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel leave event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }

        $this->channelLeave($persona, $roomObject, $userObject);
    }

    /**
     * Internally process joins
     *
     * This method has been abstracted to support multiple avenues of users
     * joining channels in the future.
     *
     * @param Persona $persona
     * @param Room $room
     * @param User $user
     * @param string $reason
     */
    protected function channelJoin(Persona $persona, Room $room, User $user, string $reason = "") {
        $room->addMember($user);
        $this->tLog(LogLevel::INFO, "{name} ({uid}) joined #{channel} ({cid}). {reason}", [
            'name' => $user->getReal(),
            'uid' => $user->getID(),
            'channel' => $room->getName(),
            'cid' => $room->getID(),
            'reason' => $reason
        ]);

        $persona->onLeave($room, $user);
    }

    /**
     * Internally process leaves
     *
     * This method has been abstracted to support multiple avenues of users
     * leaving channels in the future.
     *
     * @param Persona $persona
     * @param Room $room
     * @param User $user
     */
    protected function channelLeave(Persona $persona, Room $room, User $user) {
        $room->removeMember($user);
        $this->tLog(LogLevel::INFO, "{name} ({uid}) left #{channel} ({cid}).", [
            'name' => $user->getReal(),
            'uid' => $user->getID(),
            'channel' => $room->getName(),
            'cid' => $room->getID()
        ]);

        $persona->onJoin($room, $user);
    }

    /**
     * Handle new channel
     *
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_channel_created(Roster $roster, MessageInterface $message) {
        $room = $message->get('channel');
        $roomObject = new Room($room['id'], $room['name']);
        $roomObject->setData($room);
        $roster->map($roomObject);
    }

    /**
     * Handle channel delete
     *
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_channel_deleted(Roster $roster, MessageInterface $message) {
        try {
            $room = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel delete event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $roster->forget($room);
    }

    /**
     * Handle channel rename
     *
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_channel_rename(Roster $roster, MessageInterface $message) {
        try {
            $roomObject = $roster->getRoom('id', $message->get('channel.id'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel rename event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $oldName = $roomObject->getName();
        $roomObject->setName($message->get('channel.name'));
        $roster->map($roomObject);
        $this->tLog(LogLevel::INFO, "#{channel} ({cid}) renamed to #{newname}.", [
            'channel' => $oldName,
            'cid' => $roomObject->getID(),
            'newname' => $roomObject->getName()
        ]);
    }

    /**
     * Handle channel topic
     * 
     * @param Roster $roster
     * @param MessageInterface $message
     * @return type
     */
    public function message_channel_topic(Roster $roster, MessageInterface $message) {
        try {
            $userObject = $roster->getUser('id', $message->get('user'));
            $roomObject = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel topic event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $roomObject->setTopic($message->get('topic'));
        $roster->map($roomObject);
        $this->tLog(LogLevel::INFO, "#{channel} ({cid}) new topic: {topic}.", [
            'channel' => $roomObject->getName(),
            'cid' => $roomObject->getID(),
            'topic' => $roomObject->getTopic()
        ]);
    }

}