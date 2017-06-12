<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Addon;

use Kaecyra\ChatBot\Client\ClientInterface;

use Kaecyra\AppCommon\Addon\AbstractAddon;

/**
 * Abstract Command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class ChatAddon extends AbstractAddon {

    /**
     * Client
     * @var ClientInterface
     */
    protected $client;

    public function __construct(ClientInterface $client, $config = array()) {
        parent::__construct($config);

        $this->client = $client;
    }

}