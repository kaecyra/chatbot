<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Strategy\Message;

use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;

/**
 * Send room message strategy
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SendRoomStrategy extends AbstractStrategy {

    protected $phases = [
        'sendmessage'
    ];

}
