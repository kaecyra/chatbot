<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Core bot command router
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class CommandRouter implements LoggerAwareInterface, EventAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    public function __construct() {

    }

    /**
     * Route command to controller
     *
     * @param CommandInterface $command
     */
    public function route(CommandInterface $command) {

    }

}