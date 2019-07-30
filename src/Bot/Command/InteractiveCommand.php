<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

use Kaecyra\ChatBot\Bot\DestinationInterface;
use Kaecyra\ChatBot\Bot\IO\TextParser\TextParser;
use Kaecyra\ChatBot\Bot\User;

/**
 * Interactive command parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class InteractiveCommand extends AbstractCommand {

    /**
     * @var DestinationInterface
     */
    protected $destination;

    /**
     * @var User
     */
    protected $user;

    /**
     * Array of received TextParsers
     * @var array<TextParser>
     */
    protected $parsed;

    /**
     * @var UserDestination
     */
    protected $userDestination;

    /**
     * @var $isReady
     */
    protected $isReady;

    /**
     * InteractiveCommand constructor.
     *
     * @param UserDestination $ud
     */
    public function __construct(UserDestination $ud) {
        parent::__construct();
        $this->parsed = [];

        $this->setUserDestination($ud);
    }

    /**
     * Set/Check if command is ready to run
     *
     * @param bool
     * @return bool
     */
    public function isReady($bool = null): bool {
        if (isset($bool)) {
            $this->isReady = $bool;
        }

        return $this->isReady;
    }

    /**
     * Set UserDestination
     *
     * @param UserDestination $ud
     */
    public function setUserDestination(UserDestination $ud) {
        $this->userDestination = $ud;
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
     * Ingest a TextParser
     *
     * @param TextParser $parser
     */
    public function ingestMessage(TextParser $parser) {
        // Parse text line within context of command
        $parser->analyzeFor($this);
    }
}
