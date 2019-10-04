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
use Kaecyra\AppCommon\Store;
use Kaecyra\ChatBot\Bot\BotUser;
use Kaecyra\ChatBot\Bot\DestinationInterface;
use Kaecyra\ChatBot\Bot\IO\MessageWrapper;
use Kaecyra\ChatBot\Bot\IO\ParserInterface;
use Kaecyra\ChatBot\Bot\IO\ParserResponse;
use Kaecyra\ChatBot\Bot\Persona;
use Kaecyra\ChatBot\Bot\Room;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Bot\UserInterface;
use Kaecyra\ChatBot\Client\AsyncEvent;
use Kaecyra\ChatBot\Client\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use React\EventLoop\LoopInterface;

/**
 * Core bot command router
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class CommandRouter implements LoggerAwareInterface, EventAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    /**
     * Tick frequency
     * @var float
     */
    protected $tickFreq = 1.0;

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * RTM client
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
     * Bot persona
     * @var Persona
     */
    protected $persona;

    /**
     * Pending / active commands
     * @var array<InteractiveCommand>
     */
    protected $commands;

    /**
     * Construct
     *
     * @param ContainerInterface $container
     * @param ClientInterface $client
     * @param Roster $roster
     * @param LoopInterface $loop
     * @param Persona $persona
     */
    public function __construct(
        ContainerInterface $container,
        ClientInterface $client,
        Roster $roster,
        LoopInterface $loop,
        Persona $persona
    ) {
        $this->container = $container;
        $this->client = $client;
        $this->loop = $loop;
        $this->roster = $roster;
        $this->persona = $persona;

        $this->store = new Store;
    }

    /**
     * Execute
     */
    public function run() {
        $this->tLog(LogLevel::NOTICE, "Running CommandRouter");
        $this->client->initialize();
        $this->loop->addPeriodicTimer($this->tickFreq, [$this, 'tick']);
        $this->client->run();
    }

    /**
     * Regular tick
     */
    public function tick() {
        $this->runAsync();
        $this->expireCommands();
    }

    /**
     * Ingest messages
     *
     * This method receives direct messages and traffic-cops them to existing commands,
     * or routes them as new commands.
     *
     * @param MessageWrapper $messageWrapper
     * @param UserDestination $ud
     */
    public function ingest(MessageWrapper $messageWrapper, UserDestination $ud) {
        // Lookup or create command
        if ($this->havePendingCommand($ud)) {
            $command = $this->getPendingCommand($ud);
        } else {
            $command = $this->route($ud, $messageWrapper);

            // If no method was detected, bail out
            if (!$command) {
                // Allow hooks for individual messages
                $this->fire('directedMessage', [
                    $ud->getDestination(),
                    $ud->getUser(),
                    $messageWrapper
                ]);
                return;
            }

            $this->storePendingCommand($command);
        }

        // Pull received message into command
        $response = $command->ingestMessage($messageWrapper);

        $this->tLog(LogLevel::INFO, "Finished parsing inbound message. Result: {result}", [
            'result' => $response->getStatus()
        ]);

        // Handle error responses
        switch ($response->getStatus()) {
            // Fatal parse problem, display errors and bail
            case ParserResponse::STATUS_ERROR:
                $command->setReady(false);
                $this->removePendingCommand($ud);

                $errors = $response->getFormattedErrors($this->client);
                $errorReply = implode("\n", $errors);
                $command->sendError($errorReply);
                break;

            // Parse was fine, confirm before running
            case ParserResponse::STATUS_OK_CONFIRM:
                // Command is ready
                $command->setReady(true);

                // Command is waiting for yes/no confirmation from user
                $command->setAwaitConfirmation(true);

                // Ask for confirmation
                $final = $command->getParser()->getFinal($command);
                $command->sendConfirm($final);
                break;

            // Parse was fine, all data is present
            case ParserResponse::STATUS_OK:
                $command->setReady(true);
                $command->setAwaitConfirmation(false);
                break;

            // Parse was fine, but still missing some tokens
            case ParserResponse::STATUS_CONTINUE:
                $command->setReady(false);

                $errors = $response->getFormattedErrors($this->client);
                $continueReply = implode("\n", $errors);
                $command->sendAddressed($continueReply);
                break;

            case ParserResponse::STATUS_CANCEL:
                $this->tLog(LogLevel::INFO, "Command cancelled: {command}", [
                    'command' => $command->getCommand()
                ]);

                $command->setReady(false);
                $this->removePendingCommand($ud);
                $command->sendAddressed("Command cancelled");
                break;
        }

        // Command is ready, send it
        if ($command->isReady() && !$command->isWaiting()) {
            $this->tLog(LogLevel::INFO, "Command is ready: {command}", [
                'command' => $command->getCommand()
            ]);

            // Remove from pending user commands
            if ($command instanceof AbstractUserCommand) {
                $ud = $command->getUserDestination();
                $this->removePendingCommand($ud);
            }

            // Queue command
            $this->queueCommand($command);
        }
    }

    /**
     * Route command to controller
     *
     * @param UserDestination $ud
     * @param MessageWrapper $message
     * @return CommandInterface|boolean
     *
     */
    public function route(UserDestination $ud, MessageWrapper $message) {
        $user = $ud->getUser();

        $this->tLog(LogLevel::INFO, "Routing for '{message}' from {user}/{email}", [
            'message' => trim($message->getMessage()),
            'user' => $user->getData()['profile']['name'] ?? $user->getData()['real_name'],
            'email' => $user->getData()['profile']['email']
        ]);

        /** @var CommandInitiator $initiator */
        $initiator = array_shift($this->fireReturn('route', [$message]));
        if (!is_a($initiator, CommandInitiator::class)) {
            return false;
        }

        // Check access
        $this->tLog(LogLevel::INFO, "Checking permissions for '{command}' by {user}/{email}", [
            'command' => $initiator->getCommand(),
            'user' => $user->getData()['profile']['name'] ?? $user->getData()['real_name'],
            'email' => $user->getData()['profile']['email']
        ]);
        $roles = $initiator->getRoles();
        if (count($roles)) {
            // Check access
            $access = $this->checkAccess($ud->getUser(), $initiator->getRoles());
            if (!$access) {
                $this->tLog(LogLevel::WARNING, "Access denied to '{command}' for {user}/{email}", [
                    'command' => $initiator->getCommand(),
                    'user' => $user->getData()['profile']['name'] ?? $user->getData()['real_name'],
                    'email' => $user->getData()['profile']['email']
                ]);
                return false;
            }
        }

        /** @var InteractiveCommand $command **/
        $command = $this->container->getArgs(InteractiveCommand::class, [$ud]);
        $command->addTarget('destination', $ud->getDestination());
        $command->addTarget('user', $user);
        $command->setCommand($initiator->getCommand());
        $command->setStrategy($initiator->getStrategy());
        $command->setHandler($initiator->getCommandHandler());

        /** @var ParserInterface $parser */
        $parser = $this->container->getArgs($initiator->getParser(), [$initiator->getParserData()]);
        $command->setParser($parser);

        $this->tLog(LogLevel::INFO, "Produced command '{command}' ({guid}) for {user}/{email}", [
            'command' => $command->getCommand(),
            'guid' => $command->getGuid(),
            'user' => $user->getData()['profile']['name'] ?? $user->getData()['real_name'],
            'email' => $user->getData()['profile']['email']
        ]);

        return $command;
    }

    /**
     * Check access for user based on match with supplied roles
     *
     * @param UserInterface $user
     * @param array $roles
     * @return bool
     */
    protected function checkAccess(UserInterface $user, array $roles): bool {
        return count($this->fireReturn('checkaccess', [$user, $roles])) > 0 ? true : false;
    }

    /**
     * Execute asynchronous queue
     *
     */
    public function runAsync() {
        // Get async queue
        /** @var array<AsyncEvent> $queue */
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
                $this->tLog(LogLevel::DEBUG, "Delaying async command: {method}, {execute} > {time}", [
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
    public function queueAsync(AsyncEvent $asyncEvent) {
        $this->client->queueAsync($asyncEvent);
    }

    /**
     * Run command through container
     *
     * @param CommandInterface $command
     * @return CommandResponse
     */
    public function runCommand(CommandInterface $command) {
        $this->tLog(LogLevel::NOTICE, "Running command: {command} ({guid})", [
            'command' => $command->getCommand(),
            'guid' => $command->getGuid()
        ]);

        $commandName = $command->getCommand();
        if ($command->isExpired()) {
            $this->tLog(LogLevel::WARNING, "Tried to run expired command: {command} ({guid}) ({expiry} sec)", [
                'command' => $commandName,
                'guid' => $command->getGuid(),
                'expiry' => $command->getExpiry()
            ]);
            return new CommandResponse(CommandResponse::RESPONSE_EXPIRED);
        }

        // Call callable explicit handler
        if (is_callable($command->getHandler())) {
            $response = $this->container->call($command->getHandler(), [
                'command' => $command
            ]);
            if ($response instanceof CommandResponse) {
                return $this->handleCommandResponse($command, $response);
            }
        }

        // Look for built-in handler on the client
        if (method_exists($this->client, "command_{$commandName}")) {
            $response = $this->container->call([$this->client, "command_{$commandName}"], [
                'command' => $command
            ]);
            if ($response instanceof CommandResponse) {
                return $this->handleCommandResponse($command, $response);
            }
        }

        // Fire command event to allow post-handlers (old style)
        $this->fire('command', [$command]);
        return new CommandResponse(CommandResponse::RESPONSE_NO_HANDLER);
    }

    /**
     * Handle command response
     *
     * @param CommandInterface $command
     * @param CommandResponse $response
     * @return CommandResponse
     */
    protected function handleCommandResponse(CommandInterface $command, CommandResponse $response) {
        $this->tLog(LogLevel::NOTICE, "Command response: {response}", [
            'response' => $response->getResponse()
        ]);
        switch ($response->getResponse()) {
            case CommandResponse::RESPONSE_OK:
            case CommandResponse::RESPONSE_ERROR:
            case CommandResponse::RESPONSE_EXPIRED:
                break;

            case CommandResponse::RESPONSE_REQUEUE:
                $this->queueCommand($command, $response->getDelay(), $response->isDelta());
                break;
        }
        return $response;
    }

    /**
     * Expire old commands
     *
     */
    public function expireCommands() {
        $this->tLog(LogLevel::INFO, "{count} pending commands", [
            'count' => count($this->commands)
        ]);
        foreach ($this->commands as $command) {
            /** @var $command InteractiveCommand */
            if ($command->isExpired()) {
                $this->tLog(LogLevel::WARNING, "Auto expiring command: {command} ({guid}) ({expiry} sec)", [
                    'command' => $command->getCommand(),
                    'guid' => $command->getGuid(),
                    'expiry' => $command->getExpiry()
                ]);
                $this->removePendingCommand($command->getUserDestination());
            }
        }
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
     * @return AbstractUserCommand
     */
    public function getPendingCommand(UserDestination $ud): AbstractUserCommand {
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
     * Store pending user command
     *
     * @param AbstractUserCommand $command
     */
    public function storePendingCommand(AbstractUserCommand $command) {
        $ud = $command->getUserDestination();
        $udKey = $ud->getKey();
        $this->commands[$udKey] = $command;
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

        $messageWrapper = new MessageWrapper($message);

        // Fire "privateMessage" event
        $this->fire('privateMessage', [
            $conversationObject,
            $userObject,
            $messageWrapper
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

        $messageWrapper = new MessageWrapper($message);

        $this->fire('groupMessage', [
            $roomObject,
            $userObject,
            $messageWrapper
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
            $messageWrapper = new MessageWrapper($messageLine);

            // Generate UserDestination tag
            $ud = new UserDestination($userObject, $destinationObject);

            // Ingest message into the router
            $this->ingest($messageWrapper, $ud);
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
     * Send an addressed message to a User at a Destination
     *
     * @param DestinationInterface $destination
     * @param UserInterface $user
     * @param string $message
     */
    public function sendAddressed(DestinationInterface $destination, UserInterface $user, string $message) {
        $this->client->sendChat($destination, $this->persona->getAddressedMessage(
            $user,
            $destination,
            $message
        ));
    }

    /**
     * Send an addressed error message to a User at a Destination
     *
     * @param DestinationInterface $destination
     * @param UserInterface $user
     * @param string $error
     */
    public function sendError(DestinationInterface $destination, UserInterface $user, string $error) {
        $this->client->sendChat($destination, $this->persona->getAddressedError(
            $user,
            $destination,
            $error
        ));
    }

    /**
     * Send an addressed confirmation message to a User at a Destination
     *
     * @param DestinationInterface $destination
     * @param UserInterface $user
     * @param string $confirm
     */
    public function sendConfirm(DestinationInterface $destination, UserInterface $user, string $confirm) {
        $this->client->sendChat($destination, $this->persona->getAddressedConfirm(
            $user,
            $destination,
            $confirm
        ));
    }

    /**
     *
     * @param DestinationInterface $destination
     * @param string $message
     */
    public function sendMessage(DestinationInterface $destination, string $message) {
        $this->client->sendChat($destination, $message);
    }

    /**
     *
     *
     * @param DestinationInterface $to
     * @param array $message
     */
    public function sendFormattedMessage(DestinationInterface $to, array $message) {
        $this->client->sendFormattedChat($to, $message);
    }

}