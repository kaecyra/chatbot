<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;

/**
 * Abstract Command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractCommand implements CommandInterface, EventAwareInterface {

    use EventAwareTrait;

    /**
     * Expiry delta
     * @var int
     */
    protected $expiry;

    /**
     * Create time
     * @var int
     */
    protected $createTime;

    /**
     * Update time, when this command was last touched
     * @var int
     */
    protected $updateTime;

    /**
     * Execute method
     * @var string
     */
    protected $command;

    /**
     * Targets
     * @var array
     */
    protected $targets;

    /**
     * TODO Refactor chatbot code where the strategy property was used dynamically before, we can then switch the visibility to protected
     *
     * @var AbstractStrategy
     */
    public $strategy;

    /**
     * AbstractCommand constructor
     *
     */
    public function __construct() {
        $this->setCommand('');
        $this->createTime = time();
        $this->updateTime = time();
        $this->expiry = 300;
        $this->targets = [];
    }

    /**
     * Get method
     *
     * @return string
     */
    public function getCommand(): string {
        return $this->command;
    }

    /**
     * Set command
     *
     * @param string $command
     * @return CommandInterface
     */
    public function setCommand(string $command): CommandInterface {
        $this->command = $command;
        return $this;
    }

    /**
     * Test whether we have a command
     *
     * @return bool
     */
    public function haveCommand(): bool {
        return !empty($this->command) && strlen($this->command);
    }

    /**
     * Touch the command
     *
     * Extends updateTime, delaying expiry.
     */
    public function touch() {
        $this->updateTime = time();
    }

    /**
     * Set expiry
     *
     * @param int $delta
     * @return $this
     */
    public function setExpiry(int $delta) {
        $this->expiry = $delta;
        return $this;
    }

    /**
     * Get expiry
     *
     * @return int
     */
    public function getExpiry(): int {
        return $this->expiry;
    }

    /**
     * Check expiry
     *
     * @return bool
     */
    public function isExpired(): bool {
        return $this->expiry ? (($this->expiry + $this->updateTime) < time()) : false;
    }

    /**
     * Add a target
     *
     * @param string $name
     * @param mixed $data
     * @param boolean $multi optional. default false.
     */
    public function addTarget($name, $data, $multi = false) {
        // Prepare the target
        if ($multi) {
            if (isset($this->targets[$name])) {
                if (!is_array($this->targets[$name])) {
                    $this->targets[$name] = [$this->targets[$name]];
                }
            } else {
                $this->targets[$name] = [];
            }
            array_push($this->targets[$name], $data);
        } else {
            $this->targets[$name] = $data;
        }
    }

    /**
     * Test if we have a given target
     *
     * @param string $name
     * @return boolean
     */
    public function haveTarget($name): bool {
        return (isset($this->targets[$name]) && !empty($this->targets[$name]));
    }

    /**
     * Get target
     *
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function getTarget(string $name) {
        if ($this->haveTarget($name)) {
            return $this->targets[$name];
        } else {
            throw new \Exception("Target {$name} doesn't exist");
        }
    }

    /**
     * Set a command strategy
     *
     * @param AbstractStrategy $strategy
     */
    public function setStrategy(AbstractStrategy $strategy) {
        $this->strategy = $strategy;
    }

    /**
     * Get a command strategy
     *
     * @return AbstractStrategy
     */
    public function getStrategy(){
        return $this->strategy;
    }
}
