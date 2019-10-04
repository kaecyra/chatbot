<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Strategy;

use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;

/**
 * Startup sync strategy
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class StartupSyncStrategy extends AbstractStrategy {

    protected $phases = [
        'purge',
        'users',
        'channels',
        'join',
        'ready'
    ];

}
