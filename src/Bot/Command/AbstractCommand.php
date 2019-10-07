<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;
use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Abstract Command
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractCommand implements CommandInterface, EventAwareInterface, LoggerAwareInterface, TaggedLogInterface, \ArrayAccess {

    use LoggerBoilerTrait;
    use LoggerAwareTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    const DEFAULT_COMMAND_EXPIRY = 600;

    /**
     * Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Command guid
     * @var string
     */
    protected $guid;

    /**
     * Expiry delta
     * @var int
     */
    protected $expiry;

    /**
     * Command readiness
     * @var bool
     */
    protected $isReady;

    /**
     * Command awaiting confirmation
     * @var bool
     */
    protected $await;

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
     * Command name
     * @var string
     */
    protected $command;

    /**
     * Command handler
     * @var callable
     */
    protected $handler;

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
     * Array access data
     * @var array
     */
    protected $data;

    /**
     * AbstractCommand constructor
     *
     */
    public function __construct(ContainerInterface $container = null) {
        $this->container = $container;
        $this->setCommand('');
        $this->handler = null;
        $this->createTime = time();
        $this->updateTime = time();
        $this->expiry = self::DEFAULT_COMMAND_EXPIRY;
        $this->isReady = false;
        $this->targets = [];
        $this->data = [];

        $this->guid = $this->generateGuidv4();
    }

    /**
     * Get command GUID
     *
     * @return string
     */
    public function getGuid(): string {
        return $this->guid;
    }

    /**
     * Generate v4 GUID value
     *
     * @param null $data
     * @return string
     * @throws \Exception
     */
    protected function generateGuidv4($data = null) {
        $data = $data ?? random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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
     * Get command handler
     *
     * @return callable|null
     */
    public function getHandler(): ?callable {
        return $this->handler;
    }

    /**
     * Set command handler
     *
     * @param callable $handler
     * @return CommandInterface
     */
    public function setHandler(callable $handler): CommandInterface {
        if (!is_callable($handler)) {
            $this->tLog(LogLevel::ERROR, "Supplied command handler for '{command}' is not callable.", [
                'command' => $this->getCommand()
            ]);
            $handler = null;
        }
        $this->handler = $handler;
        return $this;
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
     * Check if command is ready to run
     *
     * @return bool
     */
    public function isReady(): bool {
        return $this->isReady;
    }

    /**
     * Set command execution readiness
     *
     * @param bool $ready
     * @return CommandInterface
     */
    public function setReady(bool $ready = true): CommandInterface {
        $this->isReady = $ready;
        return $this;
    }

    /**
     * Set waiting status
     *
     * @param bool $wait
     * @return CommandInterface
     */
    public function setAwaitConfirmation(bool $wait = true): CommandInterface {
        $this->await = $wait;
        return $this;
    }

    /**
     * Get waiting status
     *
     * @return bool
     */
    public function isWaiting(): bool {
        return $this->await === true;
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
    public function getStrategy(): AbstractStrategy {
        return $this->strategy;
    }

    /**
     * Get "OK" command response
     *
     * @return CommandResponse
     */
    public function getResponseOK(): CommandResponse {
        return new CommandResponse(CommandResponse::RESPONSE_OK);
    }

    /**
     * Get "ERROR" command response
     *
     * @return CommandResponse
     */
    public function getResponseError(): CommandResponse {
        return new CommandResponse(CommandResponse::RESPONSE_ERROR);
    }

    /**
     * Get "REQUEUE" command response
     *
     * @return CommandResponse
     */
    public function getResponseRequeue(int $delay = 0, bool $delta = true): CommandResponse {
        $r = new CommandResponse(CommandResponse::RESPONSE_REQUEUE);
        $r->setDelay($delay);
        $r->setDelta($delta);
        return $r;
    }

    /**
     * Get "EXPIRED" command response
     *
     * @return CommandResponse
     */
    public function getResponseExpired(): CommandResponse {
        return new CommandResponse(CommandResponse::RESPONSE_EXPIRED);
    }

    /**
     * Get "NO_HANDLER" command response
     *
     * @return CommandResponse
     */
    public function getResponseNoHandler(): CommandResponse {
        return new CommandResponse(CommandResponse::RESPONSE_NO_HANDLER);
    }

    /**
     * Get data by key
     *
     * @param string $key
     */
    public function &__get ($key) {
        return $this->data[$key];
    }

    /**
     * Assigns value by key
     *
     * @param string $key
     * @param mixed $value value to set
     */
    public function __set($key, $value) {
        $this->data[$key] = $value;
    }

    /**
     * Whether or not data exists by key
     *
     * @param string $key to check for
     * @return boolean
     */
    public function __isset($key) {
        return isset($this->data[$key]);
    }

    /**
     * Unset data by key
     *
     * @param string $key
     */
    public function __unset($key) {
        unset($this->data[$key]);
    }

    /**
     * Check if offset exists
     *
     * @param mixed $offset
     */
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    /**
     * Set value on offset
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Get offset value
     *
     * @param mixed $offset
     */
    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    /**
     * Unset offset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
}
