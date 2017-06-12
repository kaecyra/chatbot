<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use Kaecyra\ChatBot\Client\ClientInterface;
use Kaecyra\ChatBot\Socket\SocketClient;

use Kaecyra\ChatBot\Socket\MessageInterface;
use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Bot\Command\SimpleCommand;

use Kaecyra\ChatBot\Bot\DestinationInterface;
use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Bot\BotUser;
use Kaecyra\ChatBot\Bot\Room;
use Kaecyra\ChatBot\Bot\Roster;
use Kaecyra\ChatBot\Bot\Conversation;

use Kaecyra\ChatBot\Bot\Map\MapNotFoundException;

use Kaecyra\ChatBot\Client\Slack\Strategy\Message\SendUserStrategy;
use Kaecyra\ChatBot\Client\Slack\Strategy\Message\SendRoomStrategy;
use Kaecyra\ChatBot\Client\Slack\Strategy\Message\SendConversationStrategy;

use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;

use React\EventLoop\LoopInterface;

use Ratchet\Client\WebSocket;

use Exception;

/**
 * Slack RTM Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SlackRtmClient extends SocketClient {

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Slack Web Client API
     * @var SlackWebClient
     */
    protected $web;

    /**
     * Array of protocol handlers
     * @var array
     */
    protected $protocol = [];

    /**
     * Array of callables, by message type
     * @var array
     */
    protected $handlers = [];

    /**
     * Sent message ID
     * @var int
     */
    protected $messageSequence;

    /**
     * Start RTM client
     *
     * @param ContainerInterface $container
     * @param LoopInterface $loop
     * @param array $settings
     */
    public function __construct(
        ContainerInterface $container,
        LoopInterface $loop,
        SlackWebClient $webClient,
        array $settings
    ) {
        parent::__construct($loop, $settings);

        $this->container = $container;
        $this->web = $webClient;
    }

    /**
     * Initialize
     *
     */
    public function initialize() {
        $this->tLog(LogLevel::DEBUG, "Initializing RTM client");

        // Instantiate protocol handlers
        $this->tLog(LogLevel::NOTICE, "Adding Slack protocol handlers");
        $protocolNS = '\\Kaecyra\\ChatBot\\Client\\Slack\\Protocol';
        foreach ([
            'Connection',
            'Users',
            'Channels',
            'Messages'
        ] as $protocolHandler) {
            $handlerClass = "{$protocolNS}\\{$protocolHandler}";
            if (!$this->container->has($handlerClass)) {
                $this->tLog(LogLevel::WARNING, " missing protocol handler: {handler} ({class})", [
                    'handler' => $protocolHandler,
                    'class' => $handlerClass
                ]);
                continue;
            }

            $this->tLog(LogLevel::INFO, " protocol handler: {handler}", [
                'handler' => $protocolHandler
            ]);
            $this->protocol[$protocolHandler] = $this->container->get($handlerClass);
            $this->protocol[$protocolHandler]->start($this);
        }

        // Prepare message handling
        $this->setMessageFactory(function(string $direction) {
            $message = new \Kaecyra\ChatBot\Client\Slack\SocketMessage;

            // Tag outbound messages with a message ID
            if ($direction == self::MESSAGE_OUTBOUND) {
                $messageID = ++$this->messageSequence;
                $message->set('id', $messageID);
            }
            return $message;
        });

        // Prepare web client
        $this->web->initialize($this->settings['web']['api'], $this->settings['token']);

        // Mark configured
        $this->setState(ClientInterface::STATE_CONFIGURED);
    }

    /**
     * Override socket connect
     *
     * Slack uses a dynamic DSN which needs to be re-generated whenever a connection
     * is established.
     *
     * @param string $connectionDSN
     */
    public function connect(string $connectionDSN = null) {
        if ($connectionDSN) {
            $this->setDSN($connectionDSN);
        }

        // Check internet
        if (!$this->haveConnectivity()) {
            $this->retry = self::RETRY_RECONNECT;
            $this->tLog(LogLevel::WARNING, "No internet connection");
            return;
        }

        // Support DSN override
        $connectionDSN = $this->getDSN();
        if (!$connectionDSN) {

            $this->tLog(LogLevel::NOTICE, "Getting RTM connection DSN");
            try {
                $this->tLog(LogLevel::INFO, " request rtm session");
                $session = $this->web->rtm_connect();
                $session = $session->getBody();

                if (($session['ok'] ?? false) !== true) {
                    throw new Exception("did not receive valid rtm session");
                }

                $connectionDSN = $session['url'];

                $this->store->set('self', $session['self']);
                $this->store->set('team', $session['team']);

                // Prepare lightweight bot user
                $userObject = new BotUser($session['self']['id'], $session['self']['name']);
                $this->container->setInstance(BotUser::class, $userObject);

                $this->tLog(LogLevel::INFO, " received rtm session");
            } catch (Exception $ex) {
                $this->tLog(LogLevel::ERROR, " failed to generate new rtm session: {error}", [
                    'error' => $ex->getMessage()
                ]);
            }
        }

        $this->retry = self::RETRY_RECONNECT;

        if (!$connectionDSN) {
            return;
        }
        $this->tLog(LogLevel::NOTICE, " connecting socket client: {dsn}", [
            'dsn' => $connectionDSN
        ]);

        $this->setState(ClientInterface::STATE_CONNECTING);

        $connector = new \Ratchet\Client\Connector($this->loop);
        $connector($connectionDSN, [], [

        ])->then([$this, 'connectSuccess'], [$this, 'connectFailed']);
    }

    /**
     * Handle successful connection
     *
     * @param WebSocket $connection
     */
    public function connectSuccess(WebSocket $connection) {
        parent::connectSuccess($connection);

        // Reset outbound message ID to 0
        $this->messageSequence = 0;
    }

    /**
     * Tick
     *
     */
    public function tick() {

        // Tick the socket client
        parent::tick();

        // Perform local actions

        if (!$this->isState(ClientInterface::STATE_CONNECTED)) {
            return;
        }

        // Regular PING
        $this->callActionHandlers('ping');
        $this->callActionHandlers('verify_pings');
    }

    /**
     * Receive parsed socket message
     *
     * @param MessageInterface $message
     */
    public function onMessage(MessageInterface $message) {
        $this->callMessageHandlers($message);
    }

    /**
     * Receive socket close event
     *
     * @param int $code
     * @param string $reason
     */
    public function onClose($code = null, $reason = null) {

    }

    /**
     * Receive socket error event
     *
     * @param string $reason
     * @param WebSocket $connection
     */
    public function onError(string $reason, WebSocket $connection) {

    }

    /**
     * Add a socket message handler
     *
     * Messages are triggered externally by the arrival of a socket message.
     *
     * @param string $method
     * @param callable $callback
     */
    public function addMessageHandler(string $method, callable $callback) {
        $this->addHandler('message', $method, $callback);
    }

    /**
     * Add a socket action handler
     *
     * Actions are triggered internally and not in response to an incoming
     * message.
     *
     * @param string $method
     * @param callable $callback
     */
    public function addActionHandler(string $method, callable $callback) {
        $this->addHandler('action', $method, $callback);
    }

    /**
     * Add a generic handler
     *
     * @param string $type
     * @param string $method
     * @param callable $callback
     */
    protected function addHandler(string $type, string $method, callable $callback) {
        $handlerKey = "{$type}.{$method}";
        if (!is_array($this->handlers[$handlerKey])) {
            $this->handlers[$handlerKey] = [];
        }

        $this->handlers[$handlerKey][] = $callback;
    }

    /**
     * Convenience method to call message handlers by MessageInterface
     *
     * @param MessageInterface $message
     */
    public function callMessageHandlers(MessageInterface $message) {
        $method = $message->getMethod();
        $this->callHandlers('message', $method, [
            'message' => $message
        ]);
        if ($message->has('subtype')) {
            $subMethod = "{$method}:".$message->get('subtype');
            $this->callHandlers('message', $subMethod, [
                'message' => $message
            ]);
        }
    }

    /**
     * Convenience method to call action handlers by method
     *
     * @param string $method
     */
    public function callActionHandlers(string $method) {
        $this->callHandlers('action', $method);
    }

    /**
     * Execute a handler stack
     *
     * @param string $method
     * @param array $arguments
     */
    public function callHandlers(string $type, string $method, array $arguments = []) {
        $handlerKey = "{$type}.{$method}";
        if (!array_key_exists($handlerKey, $this->handlers) || !count($this->handlers[$handlerKey])) {
            $this->tLog(LogLevel::INFO, "Ignored unhandled {type}: {method}", [
                'type' => $type,
                'method' => $method
            ]);
            return;
        }

        // Iterate and call handlers through the container
        foreach ($this->handlers[$handlerKey] as $callback) {
            $this->container->call($callback, $arguments);
        }
    }

    /**
     * Command: roster_sync
     *
     * @param CommandInterface $command
     * @return type
     */
    public function command_roster_sync(Roster $roster, CommandInterface $command) {
        $this->tLog(LogLevel::INFO, "Roster sync");

        $strategy = $command->strategy;
        $phase = $strategy->getPhase();
        $this->tLog(LogLevel::DEBUG, " sync phase: {phase}", [
            'phase' => $phase
        ]);

        switch ($phase) {
            case 'purge':
                $roster->purge();

                // Tell the roster how to update expired rooms and users
                $roster->setTypeRefreshCallback(Room::getMapType(), function(Room $room) {
                    $command = new SimpleCommand('refresh_room');
                    $command->room = $room;
                    $this->queueCommand($command);
                });
                $roster->setTypeRefreshCallback(User::getMapType(), function(User $user) {
                    $command = new SimpleCommand('refresh_user');
                    $command->user = $user;
                    $this->queueCommand($command);
                });
                break;

            case 'users':

                // Get users

                $users = $this->web->users_list()->getBody()['members'] ?? [];
                foreach ($users as $user) {
                    $this->tLog(LogLevel::INFO, " User: {id} ({name})", [
                        'id' => $user['id'],
                        'name' => $user['name']
                    ]);

                    $this->ingestUser($roster, $user);
                }
                break;

            case 'channels':

                // Get public and private channels

                $scopes = [
                    'public' => $this->web->channels_list(false, true)->getBody()['channels'] ?? [],
                    'private' => $this->web->groups_list(false, true)->getBody()['channels'] ?? []
                ];
                foreach ($scopes as $scope => $channels) {
                    $this->tLog(LogLevel::INFO, " Indexing {scope} channels", [
                        'scope' => $scope
                    ]);
                    foreach ($channels as $channel) {
                        $this->tLog(LogLevel::INFO, " Channel: {id} ({name})", [
                            'id' => $channel['id'],
                            'name' => $channel['name']
                        ]);

                        $this->ingestRoom($roster, $channel);
                    }
                }
                break;

            case 'join':

                // Join channels

                /*
                $channels = $roster->getAll(Room::getMapType());
                foreach ($channels as $channel) {
                    $this->sendMessage('')
                }
                 */
                break;

            case 'ready':
                return;

            default:
                break;
        }

        // Queue up next phase
        $command->strategy->nextPhase();
        $this->queueCommand($command);
    }

    /**
     * Update room info
     *
     * @param Roster $roster
     * @param CommandInterface $command
     */
    public function command_refresh_room(Roster $roster, CommandInterface $command) {
        $roomID = $command->room->getID();
        if ($command->room->get('is_channel')) {
            $room = $this->web->channels_info($roomID)->getBody()['channel'];
        } else if ($command->room->get('is_group')) {
            $room = $this->web->groups_info($roomID)->getBody()['group'];
        }
        $this->ingestRoom($roster, $room);
    }

    /**
     * Ingest and map a room
     *
     * @param Roster $roster
     * @param array $room
     */
    protected function ingestRoom(Roster $roster, array $room) {
        $roomObject = new Room($room['id'], $room['name']);
        $roomObject->setTopic($room['purpose']['value'] ?? "");
        $roomObject->setData($room);

        if (isset($room['members']) && is_array($room['members'])) {
            foreach ($room['members'] as $member) {
                $mid = $member['id'];
                try {
                    $user = $roster->getUser('id', $mid);
                } catch (MapNotFoundException $ex) {
                    continue;
                }
                $roomObject->addMember($user);
            }
        }

        $roster->map($roomObject);
    }

    /**
     * Update user info
     *
     * @param Roster $roster
     * @param CommandInterface $command
     */
    public function command_refresh_user(Roster $roster, CommandInterface $command) {
        $userID = $command->user->getID();
        $user = $this->web->users_info($userID)->getBody()['user'];
        $this->ingestUser($roster, $user);
    }

    /**
     * Ingest and map a user
     *
     * @param Roster $roster
     * @param array $user
     */
    protected function ingestUser(Roster $roster, array $user) {
        if ($user['id'] == $this->store->get('self.id')) {
            $userObject = new BotUser($user['id'], $user['name']);
            $this->container->setInstance(BotUser::class, $userObject);
        } else {
            $userObject = new User($user['id'], $user['name']);
        }

        $userObject->setReal($user['real_name'] ?? "");
        $userObject->setData($user);
        if (isset($user['presence']) && !empty($user['presence'])) {
            $userObject->setPresence($user['presence']);
        }
        $roster->map($userObject);
    }

    /**
     * Request a new Conversation from the API
     *
     * @param Roster $roster
     * @param CommandInterface $command
     */
    public function command_get_conversation(Roster $roster, CommandInterface $command) {
        $userObject = $command->user;
        $im = $this->web->im_open($userObject->getID())->getBody()['channel'];

        $conversationObject = new Conversation($im['id'], $userObject);
        $roster->map($conversationObject);
        $this->tLog(LogLevel::INFO, "IM {imid} created with {name} ({uid})", [
            'imid' => $conversationObject->getID(),
            'name' => $userObject->getReal(),
            'uid' => $userObject->getID()
        ]);
    }


    public function command_send_message(Roster $roster, CommandInterface $command) {
        $this->tLog(LogLevel::DEBUG, "Send message");

        $strategy = $command->strategy;
        $phase = $strategy->getPhase();
        $this->tLog(LogLevel::DEBUG, " sync phase: {phase}", [
            'phase' => $phase
        ]);

        switch ($phase) {

            case 'getconversation':

                $userID = $command->destination->getID();
                try {
                    $conversationObject = $roster->getConversation('userid', $userID);
                    $command->destination = $conversationObject;
                    $command->strategy->setPhase('sendmessage');
                } catch (MapNotFoundException $ex) {
                    $getConversationCommand = new SimpleCommand('get_conversation');
                    $getConversationCommand->user = $command->destination;
                    $this->queueCommand($getConversationCommand);
                    $this->tLog(LogLevel::DEBUG, " requesting new user conversation: {user} ({userid})", [
                        'user' => $command->destination->getName(),
                        'userid' => $command->destination->getID()
                    ]);
                    $command->strategy->nextPhase();
                }
                break;

            case 'waitconversation':
                $userID = $command->destination->getID();
                try {
                    $conversationObject = $roster->getConversation('userid', $userID);
                } catch (MapNotFoundException $ex) {
                    // Keep waiting
                    break;
                }

                $command->destination = $conversationObject;
                $command->strategy->nextPhase();
                break;

            case 'sendmessage':
                $this->tLog(LogLevel::DEBUG, " sending message: {destid} -> {message}", [
                    'destid' => $command->destination->getID(),
                    'message' => $command->message
                ]);
                $this->web->chat_post_message($command->destination->getID(), $command->message, $command->attachments, $command->options);
                return;
                break;
        }

        // Queue up next phase
        $this->queueCommand($command);
    }

    /**
     * Send chat message
     *
     * @param DestinationInterface $destination
     * @param string $message
     * @param array $attachments
     * @param array $options
     * @throws Exception
     */
    public function sendChat(DestinationInterface $destination, string $message, array $attachments = [], array $options = []) {

        // Prepare message command
        $sendCommand = new SimpleCommand('send_message');
        $sendCommand->setExpiry(20);
        $sendCommand->format = 'simple';
        $sendCommand->message = $message;
        $sendCommand->options = $options;
        $sendCommand->attachments = $attachments;
        $sendCommand->destination = $destination;

        // Determine required strategy
        $destinationType = $destination->getMapType();
        switch ($destinationType) {
            case 'User':
                $sendCommand->strategy = new SendUserStrategy;
                break;
            case 'Room':
                $sendCommand->strategy = new SendRoomStrategy;
                break;
            case 'Conversation':
                $sendCommand->strategy = new SendConversationStrategy;
                break;
            default:
                throw new Exception("Unsupported DestinationInterface '{$destinationType}'");
                break;
        }

        // Queue command
        $this->queueCommand($sendCommand);
    }

}