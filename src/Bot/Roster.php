<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Map\Map;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Server roster manager
 *
 * This object is a map container and can store users and rooms.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Roster extends Map implements LoggerAwareInterface, EventAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Get a User
     * 
     * @param string $key
     * @param mixed $lookup
     * @return User
     */
    public function getUser(string $key, $lookup) {
        return $this->unmap('User', $key, $lookup);
    }

    /**
     * Get a Room
     *
     * @param string $key
     * @param mixed $lookup
     * @return Room
     */
    public function getRoom(string $key, $lookup) {
        return $this->unmap('Room', $key, $lookup);
    }

}