<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client;

use Kaecyra\ChatBot\Socket\MessageInterface;
use Ratchet\Client\WebSocket;

/**
 * Client interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface ClientInterface {

    const STATE_FRESH = 'fresh';
    const STATE_CONFIGURED = 'configured';
    const STATE_OFFLINE = 'offline';
    const STATE_CONNECTING = 'connecting';
    const STATE_CONNECTED = 'connected';
    const STATE_READY = 'ready';


    public function initialize();


    public function run();


    public function onMessage(MessageInterface $message);


    public function onClose($code = null, $reason = null);


    public function onError($code = null, $reason = null);

    public function emphasize(string $string): string;
}
