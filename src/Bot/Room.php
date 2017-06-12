<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Map\Mappable;
use Kaecyra\ChatBot\Bot\Map\MappableInterface;
use Kaecyra\ChatBot\Bot\Map\DataAccessTrait;

/**
 * User object
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Room extends Mappable implements DestinationInterface {

    use DataAccessTrait;

    const ROSTER_STALE = MappableInterface::STALE_RETURN_REFRESH_ASYNC;
    const ROSTER_EXPIRY = 300;

    protected $id;

    protected $name;

    protected $topic;

    protected $state;

    protected $members;

    /**
     * Extra data
     * @var array
     */
    protected $data;

    /**
     * Prepare room
     *
     * @param string|int $id
     * @param string $name
     */
    public function __construct($id, string $name) {
        $this->id = $id;
        $this->name = $name;
        $this->topic = '';

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
     * Get room ID
     * @return string|int
     */
    public function getID() {
        return $this->id;
    }

    /**
     * Get room name
     * @return string
     */
    public function getName(): string {
        return (string)$this->name;
    }

    /**
     * Set room name
     * @param string $name
     * @return Room
     */
    public function setName(string $name): Room {
        $this->name = $name;
        return $this;
    }

    /**
     * Get room topic
     * @return string
     */
    public function getTopic(): string {
        return (string)$this->topic;
    }

    /**
     * Set room topic
     * @param string $topic
     * @return Room
     */
    public function setTopic(string $topic): Room {
        $this->topic = $topic;
        return $this;
    }

    /**
     * Get room state
     * @return string
     */
    public function getState(): string {
        return (string)$this->state;
    }

    /**
     * Set room state
     * @param string $state
     * @return Room
     */
    public function setState(string $state): Room {
        $this->state = $state;
        return $this;
    }

    /**
     * Add member to room
     * @param User $user
     */
    public function addMember(User $user) {
        $this->members[$user->getID()] = true;
    }

    /**
     * Remove member from room
     * @param User $user
     */
    public function removeMember(User $user) {
        unset($this->members[$user->getID()]);
    }

    /**
     * Get list of userid that are in the channel
     * @return array
     */
    public function getMembers(): array {
        return array_keys($this->members);
    }

    /**
     * Get room data
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Set room data
     * @param array $data
     * @return Room
     */
    public function setData(array $data): Room {
        $this->data = $data;
        return $this;
    }

}