<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Client\ClientInterface;
use Kaecyra\ChatBot\Client\AsyncEvent;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Bot\Command\SimpleCommand;
use Kaecyra\ChatBot\Bot\Command\FluidCommand;

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
     * Roster
     * @var Roster
     */
    protected $roster;

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
     * @param Roster $roster
     * @param LoopInterface $loop
     */
    public function __construct(
        ContainerInterface $container,
        ClientInterface $client,
        Roster $roster,
        LoopInterface $loop
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->loop = $loop;
        $this->roster = $roster;

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

        $this->tLog(LogLevel::INFO, "Running async queue");

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
        if ($command->isExpired()) {
            $this->tLog(LogLevel::WARNING, "Expired command: {method} ({expiry} sec)", [
                'method' => $commandName,
                'expiry' => $command->getExpiry()
            ]);
            return;
        }

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

    /**
     * Get the roster
     *
     * @return Roster
     */
    public function getRoster(): Roster {
        return $this->roster;
    }


    public function onJoin(Room $room, User $user) {
        $this->fire('join', [
            $room,
            $user
        ]);
    }


    public function onLeave(Room $room, User $user) {
        $this->fire('leave', [
            $room,
            $user
        ]);
    }


    public function onPresenceChange(User $user) {
        $this->fire('presence', [
            $user
        ]);
    }

    /**
     * Handle a direct message
     *
     * @param User $userObject
     * @param string $message
     */
    public function onDirectMessage(User $userObject, string $message) {

        // Don't do anything for blank chats
        $body = rtrim(trim($message), '.?,');
        if (!strlen($body)) {
            return;
        }

        $conversationObject = $this->roster->getConversation('userid', $userObject->getID());

        $this->fire('privateMessage', [
            $conversationObject,
            $userObject,
            $message
        ]);

        $this->onDirectedMessage($conversationObject, $userObject, $message);
    }

    /**
     * Handle a group message
     *
     * @param Room $roomObject
     * @param User $userObject
     * @param string $message
     */
    public function onGroupMessage(Room $roomObject, User $userObject, string $message) {
        $this->fire('groupMessage', [
            $roomObject,
            $userObject,
            $message
        ]);

        $botUserObject = $this->container->get(BotUser::class);
        $botNames = [
            strtolower($botUserObject->getName()),
            strtolower($botUserObject->getReal())
        ];

        foreach ($botNames as $botName) {

            $body = $message;

            // If our name is in the message, look for it at either end
            if (stristr($body, $botName)) {
                $command = null;

                $matchedNick = false;

                // Left side exclude
                $matches = 0;
                $body = preg_replace("/^(@?(?:{$botName}),? ?)/i", '', $body, -1, $matches);
                $matchedNick = $matchedNick || $matches > 0;
                $body = preg_replace("/(,? ?@?(?:{$botName})\??)$/i", '', $body, -1, $matches);
                $matchedNick = $matchedNick || $matches > 0;

                $command = $body;

                // If this was directed at us, parse for commands
                if (!is_null($command) && $matchedNick) {
                    $this->onDirectedMessage($roomObject, $userObject, $command);
                    break;
                }
            }

        }
    }

    /**
     * Handle a bot-directed message
     *
     * @param DestinationInterface $destinationObject
     * @param User $userObject
     * @param string $message
     * @return type
     */
    public function onDirectedMessage(DestinationInterface $destinationObject, User $userObject, string $message) {

        $this->tLog(LogLevel::INFO, "Directed: [{destination}][{name}] {text}", [
            'destination' => $destinationObject->getID(),
            'name' => $userObject->getReal(),
            'text' => $message
        ]);

        // Parse command string into state
        $command = $this->container->getArgs(FluidCommand::class, [$message]);
        $command->addTarget('destination', $destinationObject);
        $command->addTarget('user', $userObject);
        $command->analyze();

        $this->fire('directedMessage', [
            $destinationObject,
            $userObject,
            $command
        ]);

        // If no method was provided, bail out
        if (!$command->getCommand()) {
            return;
        }
        $this->tLog(LogLevel::NOTICE, "Command: {command}", [
            'command' => $command->getCommand()
        ]);

        // Execute command
        $this->runCommand($command);
    }

    /**
     * Connection close
     *
     * @param int $code
     * @param string $reason
     */
    public function onClose($code, $reason) {
        $this->fire('connectionClose');
    }

    /**
     *
     * @param DestinationInterface $to
     * @param string $message
     */
    public function sendMessage(DestinationInterface $to, string $message) {
        $this->client->sendChat($to, $message);
    }

    /**
     *
     * @param DestinationInterface $to
     * @param array $message
     */
    public function sendFormattedMessage(DestinationInterface $to, array $message) {
        $this->client->sendFormattedChat($to, $message);
    }

}