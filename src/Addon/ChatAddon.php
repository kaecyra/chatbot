<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Addon;

use Kaecyra\ChatBot\Client\ClientInterface;

use Kaecyra\AppCommon\Addon\AbstractAddon;
use Kaecyra\AppCommon\Store;

/**
 * Abstract Chat Addon
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

    /**
     * Data store
     * @var Store
     */
    protected $store;

    /**
     * ChatAddon constructor.
     *
     * @param ClientInterface $client
     * @param array $config
     */
    public function __construct(ClientInterface $client, $config = []) {
        parent::__construct($config);

        $this->client = $client;
        $this->store = new Store;
    }

}