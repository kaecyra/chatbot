<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use \Exception;

/**
 * Startup sync strategy
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class StartupSyncStrategy {

    protected $phases = [
        'purge',
        'channels',
        'users',
        'ready'
    ];

    /**
     * Current phase
     * @var int
     */
    protected $phase;


    public function __construct() {
        $this->reset();
    }

    /**
     * Reset startup phase
     *
     */
    public function reset() {
        $this->phase = 1;
    }

    /**
     * Get current phase
     *
     * @return string
     */
    public function getPhase(): string {
        return $this->phases[($this->phase-1)];
    }

    /**
     * Get next phase
     *
     * @return string
     */
    public function nextPhase(): string {
        $this->phase++;
        if ($this->phase > count($this->phase)) {
            return false;
        }
        return $this->phases[($this->phase-1)];
    }

}