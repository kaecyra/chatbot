<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Strategy;

use \Exception;

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