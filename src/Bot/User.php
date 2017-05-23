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
class User extends Mappable {

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

        $this->setMappedProperties([
            'id' => function(){return $this->getID();},
            'name' => function(){return $this->getName();}
        ]);
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