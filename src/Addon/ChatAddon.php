<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Addon;

use Kaecyra\AppCommon\Addon\AbstractAddon;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;
use Kaecyra\AppCommon\Store;
use Kaecyra\ChatBot\Client\ClientInterface;

/**
 * Abstract Chat Addon
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class ChatAddon extends AbstractAddon implements TaggedLogInterface, PersonaAwareInterface {

    use TaggedLogTrait;
    use PersonaAwareTrait;

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