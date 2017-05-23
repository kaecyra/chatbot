<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;

use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use \Exception;

/**
 * Abstract protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractProtocolHandler implements LoggerAwareInterface, TaggedLogInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Register callbacks
     *
     */
    abstract public function start(SlackRtmClient $client);

}