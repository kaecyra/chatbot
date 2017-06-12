<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Map\Map;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

/**
 * Server roster manager
 *
 * This object is a map container and can store users and rooms.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Roster extends Map implements EventAwareInterface {

    use EventAwareTrait;

    /**
     * Get a User
     *
     * @param string $key
     * @param mixed $lookup
     * @return User|false
     */
    public function getUser(string $key, $lookup) {
        return $this->unmap(User::getMapType(), $key, $lookup);
    }

    /**
     * Get a Conversation
     *
     * @param string $key
     * @param mixed $lookup
     * @return User|false
     */
    public function getConversation(string $key, $lookup) {
        return $this->unmap(Conversation::getMapType(), $key, $lookup);
    }

    /**
     * Get a Room
     *
     * @param string $key
     * @param mixed $lookup
     * @return Room|false
     */
    public function getRoom(string $key, $lookup) {
        return $this->unmap(Room::getMapType(), $key, $lookup);
    }

}