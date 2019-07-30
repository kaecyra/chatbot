<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Strategy\Message;

use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;

/**
 * Send user message strategy
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SendConversationStrategy extends AbstractStrategy {

    protected $phases = [
        'sendmessage'
    ];

}
