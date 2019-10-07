<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

/**
 * Bot self user
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class BotUser extends User {

    /**
     * Get mapping name
     *
     * @return string
     */
    public static function getMapType(): string {
        return 'User';
    }

}