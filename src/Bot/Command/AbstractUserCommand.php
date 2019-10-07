<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

use Kaecyra\ChatBot\Bot\IO\MessageWrapper;
use Kaecyra\ChatBot\Bot\IO\ParserResponse;

/**
 * Abstract User Command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractUserCommand extends AbstractCommand {

    /**
     * @var UserDestination
     */
    protected $userDestination;

    /**
     * Set UserDestination
     *
     * @param UserDestination $ud
     * @return CommandInterface
     */
    public function setUserDestination(UserDestination $ud): AbstractUserCommand {
        $this->userDestination = $ud;
        return $this;
    }

    /**
     * Get UserDestination
     *
     * @return UserDestination
     */
    public function getUserDestination(): UserDestination {
        return $this->userDestination;
    }

    /**
     * Ingest command message
     *
     * @param MessageWrapper $message
     * @return ParserResponse
     */
    abstract public function ingestMessage(MessageWrapper $message): ParserResponse;

}