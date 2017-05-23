<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Client\AsyncEvent;

use Kaecyra\AppCommon\Store;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

use Psr\Log\LogLevel;

/**
 * Abstract client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class AbstractClient implements ClientInterface, LoggerAwareInterface, TaggedLogInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Client settings
     * @var array
     */
    protected $settings;

    /**
     * Data store
     * @var Store
     */
    protected $store;

    /**
     * Connection state
     * @var string
     */
    protected $state = ClientInterface::STATE_FRESH;

    /**
     *
     * @param array $settings
     */
    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->store = new Store;
    }

    /**
     * Set connection state
     *
     * @param string $state
     * @return ClientInterface
     */
    public function setState(string $state) {
        $changed = $this->state != $state;
        $oldState = $this->state;
        $this->state = $state;
        if ($changed) {
            $this->fire('client_state_changed', [$oldState, $state]);
        }
        return $this;
    }

    /**
     * Get connection state
     *
     * @return string
     */
    public function getState(): string {
        return $this->state;
    }

    /**
     * Check the current state
     *
     * @param string $state
     * @return bool
     */
    public function isState(string $state): bool {
        return $this->state == $state;
    }

    /**
     * Test if client is ready
     *
     * @return boolean
     */
    public function isReady() {
        return $this->isState(ClientInterface::STATE_READY);
    }

    /**
     * Get data store
     *
     * @return Store
     */
    public function getStore(): Store {
        return $this->store;
    }

    /**
     * Support async ticking
     *
     */
    public function tick() {

    }

    /**
     * Get async queue
     *
     */
    public function getAsyncQueue(): array {
        return (array)$this->store->get('queue');
    }

    /**
     * Clear async queue
     *
     */
    public function clearAsyncQueue() {
        $this->store->set('queue', []);
    }

    /**
     * Directly queue an AsyncEvent
     *
     * @param AsyncEvent $asyncEvent
     * @param bool $reset optional. reset async to new state, without releasing command.
     */
    public function queueAsync(AsyncEvent $asyncEvent) {
        if (!$asyncEvent->getQueued()) {
            $this->tLog(LogLevel::INFO, "Stashed async command: {command}", [
                'command' => $asyncEvent->getCommand()->getCommand()
            ]);
        }
        $asyncEvent->wasQueued();
        $this->store->push('queue', $asyncEvent);
    }

    /**
     * Queue a command for async execution
     *
     * @param CommandInterface $command
     * @param integer $executeAt optional. how long to wait before execution. default ASAP (at most, $tickFreq seconds).
     * @param boolean $delta optional. treat $delay as a delta of seconds, not the final time. default true.
     */
    public function queueCommand(CommandInterface $command, $executeAt = 0, $delta = true) {
        $asyncEvent = new AsyncEvent($command);
        $asyncEvent->setExecute($executeAt, $delta);
        $this->queueAsync($asyncEvent);
    }

}