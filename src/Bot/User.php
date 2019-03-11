<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Map\Mappable;
use Kaecyra\ChatBot\Bot\Map\MappableInterface;
use Kaecyra\ChatBot\Bot\Map\DataAccessTrait;

use Kaecyra\ChatBot\Bot\Conversation;

use Kaecyra\ChatBot\Bot\DestinationInterface;

/**
 * User object
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class User extends Mappable implements UserInterface, DestinationInterface {

    use DataAccessTrait;

    const ROSTER_STALE = MappableInterface::STALE_RETURN_REFRESH_ASYNC;
    const ROSTER_EXPIRY = 300;

    protected $id;

    protected $name;

    protected $real;

    protected $presence;

    /**
     * Extra data
     * @var array
     */
    protected $data;

    /**
     * Prepare user
     */
    public function __construct($id, string $name) {
        $this->id = $id;
        $this->name = $name;

        $this->data = [];

        $this->setMappedProperties('id', [
            'id' => function(){return $this->getID();},
            'name' => function(){return $this->getName();}
        ]);
    }

    /**
     * Override stale object handling
     * @return string
     */
    public function getStaleHandling(): string {
        return self::ROSTER_STALE;
    }

    /**
     * Override object expiry
     * @return int
     */
    public function getExpiry(): int {
        return self::ROSTER_EXPIRY;
    }

    /**
     * Get user ID
     * @return string|int
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Set user name
     * @param string $name
     * @return User
     */
    public function setName(string $name): User {
        $this->name = $name;
        return $this;
    }

    /**
     * Get user name
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Set user real name
     * @param string $real
     * @return User
     */
    public function setReal(string $real): User {
        $this->real = $real;
        return $this;
    }

    /**
     * Get user real name
     * @return string
     */
    public function getReal(): string {
        return $this->real;
    }

    /**
     * Get user presence
     * @return string
     */
    public function getPresence(): string {
        return $this->presence;
    }

    /**
     * Set user presence
     * @param string $presence
     * @return User
     */
    public function setPresence(string $presence): User {
        $this->presence = $presence;
        return $this;
    }

    /**
     * Get user data
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Set user data
     * @param array $data
     * @return User
     */
    public function setData(array $data): User {
        $this->data = $data;
        return $this;
    }

}