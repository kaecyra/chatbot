<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

/**
 * User Interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface UserInterface {

    public function getID();

    /**
     * Set user name
     * @param string $name
     * @return UserInterface
     */
    public function setName(string $name): UserInterface;

    /**
     * Get user name
     * @return string
     */
    public function getName(): string;

}