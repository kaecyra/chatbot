<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

/**
 * Usable as a message destination
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface DestinationInterface {

    public function getID();

}