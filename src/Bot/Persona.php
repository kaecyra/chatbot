<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot;

use Kaecyra\ChatBot\Bot\Command\InteractiveCommand;
use Kaecyra\ChatBot\Bot\Command\UserDestination;
use Kaecyra\ChatBot\Bot\IO\TextParser\TextParser;
use Kaecyra\ChatBot\Client\ClientInterface;
use Kaecyra\ChatBot\Client\AsyncEvent;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Bot\Command\SimpleCommand;

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
     * Pending / active commands
     * @var array<InteractiveCommand>
     */
    protected $commands;

    /**
     * Command Router
     * @var CommandRouter
     */
    protected $router;

    /**
     *
     * @param ContainerInterface $container
     * @param ClientInterface $client
     * @param CommandRouter $router
     * @param Roster $roster
     * @param LoopInterface $loop
     */
    public function __construct(
        ContainerInterface $container,
        ClientInterface $client,
        CommandRouter $router,
        Roster $roster,
        LoopInterface $loop
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->router = $router;
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
        $this->expireCommands();
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

        // Look for built-in handler on the client
        if (method_exists($this->client, "command_{$commandName}")) {
            $this->container->call([$this->client, "command_{$commandName}"], [
                'command' => $command
            ]);
        }

        // Fire command event to solicit other handlers
        $calls = $this->fireReturn('command', [$command]);
        foreach ($calls as $call) {
            $this->container->call($call, [
                'command' => $command
            ]);
        }
    }

    /**
     * Expire old commands
     *
     */
    public function expireCommands() {
        foreach ($this->commands as $command) {
            /** @var $command InteractiveCommand */
            if ($command->isExpired()) {
                $this->removePendingCommand($command->getUserDestination());
            }
        }
    }

    /**
     * Gather schema items for command
     *
     * @param CommandInterface $command
     */
    public function gatherSchema(CommandInterface $command) {

    }

    /**
     * Check if we have a pending command for given UserDestination
     *
     * @param UserDestination $ud
     * @return bool
     */
    public function havePendingCommand(UserDestination $ud) {
        $udKey = $ud->getKey();
        return array_key_exists($udKey, $this->commands);
    }

    /**
     * Retrieve pending command for UserDestination
     *
     * @param UserDestination $ud
     * @return mixed
     */
    public function getPendingCommand(UserDestination $ud) {
        $udKey = $ud->getKey();
        return $this->commands[$udKey];
    }

    /**
     * Unset pending command for UserDestination
     *
     * @param UserDestination $ud
     */
    public function removePendingCommand(UserDestination $ud) {
        $udKey = $ud->getKey();
        unset($this->commands[$udKey]);
    }

    /**
     * Handle a direct message (IM)
     *
     * @param User $userObject
     * @param string $message
     */
    public function onDirectMessage(User $userObject, string $message) {

        // Don't do anything for blank chats
        $checkBody = rtrim(trim($message), '.?,');
        if (!strlen($checkBody)) {
            return;
        }

        $conversationObject = $this->roster->getConversation('userid', $userObject->getID());

        // Fire "privateMessage" event
        $this->fire('privateMessage', [
            $conversationObject,
            $userObject,
            $message
        ]);

        // Fire "directedMessage" event
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

        // Don't do anything for blank chats
        $checkBody = rtrim(trim($message), '.?,');
        if (!strlen($checkBody)) {
            return;
        }

        $this->fire('groupMessage', [
            $roomObject,
            $userObject,
            $message
        ]);

        $botUserObject = $this->container->get(BotUser::class);
        $botUserMentionTag = "<@{$botUserObject->getID()}>";
        $body = $message;

        // Look for the bot @mention tag anywhere in the command
        if (stripos($body, $botUserMentionTag) !== false) {
            $command = null;

            // Remove the bot user tag and useless chars from the command
            $body = preg_replace("/((?:^| ){$botUserMentionTag}\.?,?\??)/i", '', $body, -1);
            $command = $body;

            // If this was directed at us, parse for commands
            if (!is_null($command)) {
                $this->onDirectedMessage($roomObject, $userObject, $command);
            }
        }

    }

    /**
     * Handle a bot-directed message
     *
     * @param DestinationInterface $destinationObject
     * @param User $userObject
     * @param string $message
     */
    public function onDirectedMessage(DestinationInterface $destinationObject, User $userObject, string $message) {

        $this->tLog(LogLevel::INFO, "Directed: [{destination}][{name}] {text}", [
            'destination' => $destinationObject->getID(),
            'name' => $userObject->getReal(),
            'text' => $message
        ]);

        $messageLines = explode("\n", $message);
        foreach ($messageLines as $messageLine) {
            // Prepare text parser
            $parser = new TextParser($messageLine);

            // Generate UserDestination tag
            $ud = new UserDestination($userObject, $destinationObject);

            // Lookup or create command
            if ($this->havePendingCommand($ud)) {
                $command = $this->getPendingCommand($ud);
            } else {
                // Parse command string into state
                $command = $this->container->getArgs(InteractiveCommand::class, [$ud]);
                $command->addTarget('destination', $destinationObject);
                $command->addTarget('user', $userObject);
            }

            $command->ingestMessage($parser);

            if (!$command->getCommand()) {
                $this->router->route($command);
            }

            // If no method was detected, bail out
            if (!$command->getCommand()) {

                // Allow hooks for individual message
                $this->fire('directedMessage', [
                    $destinationObject,
                    $userObject,
                    $parser
                ]);

                continue;
            }

            if (!$command->isReady()) {
                $this->tLog(LogLevel::NOTICE, "Gather Schema: {command}", [
                    'command' => $command->getCommand()
                ]);

                $this->gatherSchema($command);
            } else {
                $this->tLog(LogLevel::NOTICE, "Command: {command}", [
                    'command' => $command->getCommand()
                ]);

                // Execute command
                $this->runCommand($command);
            }
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

    /**
     * Handle join event
     *
     * @param Room $room
     * @param User $user
     */
    public function onJoin(Room $room, User $user) {
        $this->fire('join', [
            $room,
            $user
        ]);
    }

    /**
     * Handle leave event
     *
     * @param Room $room
     * @param User $user
     */
    public function onLeave(Room $room, User $user) {
        $this->fire('leave', [
            $room,
            $user
        ]);
    }

    /**
     * Handle user presence change
     *
     * @param User $user
     */
    public function onPresenceChange(User $user) {
        $this->fire('presence', [
            $user
        ]);
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
