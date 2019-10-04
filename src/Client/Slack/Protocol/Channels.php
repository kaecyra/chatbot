<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;
use Kaecyra\ChatBot\Bot\BotUser;
use Kaecyra\ChatBot\Bot\Command\CommandRouter;
use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;
use Kaecyra\ChatBot\Bot\Room;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Client\Slack\WebClientAwareInterface;
use Kaecyra\ChatBot\Client\Slack\WebClientAwareTrait;
use Kaecyra\ChatBot\Socket\MessageInterface;
use Psr\Log\LogLevel;

/**
 * Channels protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Channels extends AbstractProtocolHandler implements WebClientAwareInterface {

    use WebClientAwareTrait;

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {

        $client->addMessageHandler('channel_left', [$this, 'message_channel_left']);

        $client->addMessageHandler('member_joined_channel', [$this, 'message_member_joined_channel']);
        $client->addMessageHandler('member_left_channel', [$this, 'message_member_left_channel']);

        $client->addMessageHandler('channel_created', [$this, 'message_channel_created']);
        $client->addMessageHandler('channel_deleted', [$this, 'message_channel_deleted']);
        $client->addMessageHandler('channel_rename', [$this, 'message_channel_rename']);

        $client->addMessageHandler('message:channel_topic', [$this, 'message_channel_topic']);
    }

    /**
     * Ingest and map a room
     *
     * @param Roster $roster
     * @param string $room
     * @throws
     */
    protected function ingestRoom(Roster $roster, $room) {
        try {
            $roomObject = $roster->getRoom('id', $room);
        } catch (MapNotFoundException $ex) {
            $webClient = $this->getWebClient();
            $room = $webClient->conversations_info($room);
            $roomObject = new Room($room['id'], $room['name']);
            $roomObject->setTopic($room['purpose']['value'] ?? "");
            $roomObject->setData($room);
        }
        if (isset($room['members']) && is_array($room['members'])) {
            foreach ($room['members'] as $member) {
                $mid = $member['id'];
                try {
                    $user = $roster->getUser('id', $mid);
                } catch (MapNotFoundException $ex) {
                    continue;
                }
                $roomObject->addMember($user);
            }
        }

        $roster->map($roomObject);
    }

    /**
     * Handle bot leaves
     *
     * @param CommandRouter $router
     * @param Roster $roster
     * @param BotUser $user
     * @param MessageInterface $message
     */
    public function message_channel_left(CommandRouter $router, Roster $roster, BotUser $user, MessageInterface $message) {
        $this->ingestRoom($roster, $message->get('channel'));
        try {
            $roomObject = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel self leave event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }
        $this->channelLeave($router, $roomObject, $user);
    }

    /**
     * Handle joins (for both user and bot)
     *
     * @param CommandRouter $router
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_member_joined_channel(CommandRouter $router, Roster $roster, MessageInterface $message) {
        $this->ingestRoom($roster, $message->get('channel'));
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

        $this->channelJoin($router, $roomObject, $userObject, $reason);
    }

    /**
     * Handle leaves
     *
     * @param CommandRouter $router
     * @param Roster $roster
     * @param MessageInterface $message
     */
    public function message_member_left_channel(CommandRouter $router, Roster $roster, MessageInterface $message) {
        $this->ingestRoom($roster, $message->get('channel'));
        try {
            $userObject = $roster->getUser('id', $message->get('user'));
            $roomObject = $roster->getRoom('id', $message->get('channel'));
        } catch (MapNotFoundException $ex) {
            $this->tLog(LogLevel::WARNING, "Could not process channel leave event. {reason}", [
                'reason' => $ex->getMessage()
            ]);
            return;
        }

        $this->channelLeave($router, $roomObject, $userObject);
    }

    /**
     * Internally process joins
     *
     * This method has been abstracted to support multiple avenues of users
     * joining channels in the future.
     *
     * @param CommandRouter $router
     * @param Room $room
     * @param User $user
     * @param string $reason
     */
    protected function channelJoin(CommandRouter $router, Room $room, User $user, string $reason = "") {
        $room->addMember($user);
        $this->tLog(LogLevel::INFO, "{name} ({uid}) joined #{channel} ({cid}). {reason}", [
            'name' => $user->getReal(),
            'uid' => $user->getID(),
            'channel' => $room->getName(),
            'cid' => $room->getID(),
            'reason' => $reason
        ]);

        $router->onJoin($room, $user);
    }

    /**
     * Internally process leaves
     *
     * This method has been abstracted to support multiple avenues of users
     * leaving channels in the future.
     *
     * @param CommandRouter $router
     * @param Room $room
     * @param User $user
     */
    protected function channelLeave(CommandRouter $router, Room $room, User $user) {
        $room->removeMember($user);
        $this->tLog(LogLevel::INFO, "{name} ({uid}) left #{channel} ({cid}).", [
            'name' => $user->getReal(),
            'uid' => $user->getID(),
            'channel' => $room->getName(),
            'cid' => $room->getID()
        ]);

        $router->onLeave($room, $user);
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
