<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

/**
 * Conversation object
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Conversation extends Room {

    protected $user;

    /**
     * Prepare conversation
     *
     * @param string|int $id
     * @param User $user
     */
    public function __construct($id, User $user) {
        $this->id = $id;
        $this->user = $user;

        $this->setMappedProperties('id', [
            'id' => function(){return $this->getID();},
            'user' => function(){return $this->getUserName();},
            'userid' => function(){return $this->getUserID();}
        ]);
    }

    /**
     * Get user
     * @return User
     */
    public function getUser(): User {
        return $this->user;
    }

    /**
     * Get user name
     * @return string
     */
    public function getUserName(): string {
        return $this->user->getName();
    }

    /**
     * Get user ID
     * @return mixed
     */
    public function getUserID() {
        return $this->user->getID();
    }

}