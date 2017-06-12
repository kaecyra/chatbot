<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Strategy;

use \Exception;

/**
 * Abstract strategy
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class AbstractStrategy {

    /**
     * Strategy phases
     * @var array
     */
    protected $phases = [];

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


    public function setPhase(string $phaseKey) {
        $phase = array_search($phaseKey, $this->phases);
        if ($phase === false) {
            return;
        }
        $this->phase = $phase+1;
    }

}