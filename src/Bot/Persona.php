<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Client\ClientInterface;
use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Client\AsyncEvent;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Kaecyra\AppCommon\Store;

use Psr\Container\ContainerInterface;

use \React\EventLoop\LoopInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * Core bot persona
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Persona implements LoggerAwareInterface, EventAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Socket client
     * @var ClientInterface
     */
    protected $client;

    /**
     * Event loop
     * @var LoopInterface
     */
    protected $loop;

    /**
     * Tick frequency
     * @var float
     */
    protected $tickFreq = 1.0;

    /**
     * Data store
     * @var Store
     */
    protected $store;

    /**
     *
     * @param ContainerInterface $container
     * @param ClientInterface $client
     */
    public function __construct(
        ContainerInterface $container,
        ClientInterface $client,
        LoopInterface $loop
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->loop = $loop;

        $this->store = new Store;
    }

    /**
     * Execute
     */
    public function run() {
        $this->tLog(LogLevel::NOTICE, "Running persona");
        $this->client->initialize();
        $this->loop->addPeriodicTimer($this->tickFreq, [$this, 'tick']);
        $this->client->run();
    }

    /**
     * Periodic tick
     *
     */
    public function tick() {
        $this->runAsync();
    }

    /**
     * Execute asynchronous queue
     *
     */
    public function runAsync() {
        // Get async queue
        $queue = $this->client->getAsyncQueue(true);
        $this->client->clearAsyncQueue();

        if (!count($queue)) {
            return;
        }

        $this->tLog(LogLevel::NOTICE, "Running async queue");

        // Iterate over queue and fire off commands
        foreach ($queue as $asyncEvent) {

            // Not ready yet? Put back into queue
            if (!$asyncEvent->canExecute()) {
                $this->tLog(LogLevel::INFO, "Delaying async command: {method}, {execute} > {time}", [
                    'method' => $asyncEvent->getCommand()->getCommand(),
                    'execute' => $asyncEvent->getExecute(),
                    'time' => time()
                ]);
                $this->queueAsync($asyncEvent);
                continue;
            }

            $this->tLog(LogLevel::INFO, "Execute async command: {method}", [
                'method' => $asyncEvent->getCommand()->getCommand()
            ]);
            $this->runCommand($asyncEvent->getCommand());
        }
    }

    /**
     * Proxy method to queue async commands on client
     *
     * @param CommandInterface $command
     * @param integer $delay optional. how long to wait before execution. default ASAP (at most, $tickFreq seconds).
     * @param boolean $delta optional. treat $delay as a delta of seconds, not the final time. default true.
     */
    public function queueCommand(CommandInterface $command, $delay = 0, $delta = true) {
        $this->client->queueCommand($command, $delay, $delta);
    }

    /**
     * Proxy method to queue async events on client
     *
     * @param AsyncEvent $asyncEvent
     */
    public function queueAsync(\Kaecyra\ChatBot\Client\AsyncEvent $asyncEvent) {
        $this->client->queueAsync($asyncEvent);
    }

    /**
     * Run command through container
     *
     * Fire an event to collect callables that match the supplied command. Call
     * those callables through the DIC.
     *
     * @param CommandInterface $command
     */
    public function runCommand(CommandInterface $command) {
        $commandName = $command->getCommand();
        if (method_exists($this->client, "command_{$commandName}")) {
            $this->container->call([$this->client, "command_{$commandName}"], [
                'command' => $command
            ]);
        }

        $calls = $this->fireReturn('command', [$command]);
        foreach ($calls as $call) {
            $this->container->call($call, [
                'command' => $command
            ]);
        }
    }


    public function onJoin(Room $room, User $user) {

    }


    public function onLeave(Room $room, User $user) {

    }


    public function presenceChange(User $user) {

    }

    /**
     *
     * @param User $user
     * @param Message $message
     */
    public function onDirectMessage(User $user, Message $message) {

    }

    /**
     *
     * @param Room $room
     * @param User $user
     * @param Message $message
     */
    public function onGroupMessage(Room $room, User $user, Message $message) {

    }

    /**
     *
     * @param int $code
     * @param string $reason
     */
    public function onClose($code, $reason) {

    }

    /**
     *
     * @param User $user
     * @param Message $message
     */
    public function sendMessage(User $user, Message $message) {

    }


}