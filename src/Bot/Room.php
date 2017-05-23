<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Map\Mappable;

/**
 * Core bot persona
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Room extends Mappable {

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

        $this->setMappedProperties([
            'id' => function(){return $this->getID();},
            'name' => function(){return $this->getName();}
        ]);
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