<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Addon;

use Kaecyra\ChatBot\Client\ClientInterface;
use Kaecyra\ChatBot\Bot\ControllerInterface;

/**
 * Abstract Chat Controller
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class ChatController implements ControllerInterface {

    /**
     * Client
     * @var ClientInterface
     */
    protected $client;

    public function __construct(ClientInterface $client) {
        $this->client = $client;
    }

}