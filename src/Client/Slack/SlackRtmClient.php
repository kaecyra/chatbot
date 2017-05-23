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

use Kaecyra\ChatBot\Bot\User;
use Kaecyra\ChatBot\Bot\Room;
use Kaecyra\ChatBot\Bot\Roster;

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
            'IM'
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

        // Support DSN override
        $connectionDSN = $this->getDSN();
        if (!$connectionDSN) {

            $this->tLog(LogLevel::NOTICE, "Getting RTM connection DSN");
            try {
                $this->tLog(LogLevel::INFO, " request rtm session");
                $session = $this->web->rtm_connect();
                $session = $session->getBody();

                $connectionDSN = $session['url'];

                $this->tLog(LogLevel::INFO, " received rtm session");
            } catch (Exception $ex) {
                $this->tLog(LogLevel::ERROR, " failed to generate new rtm session: {error}", [
                    'error' => $ex->getMessage()
                ]);
            }

        }

        $this->retry = self::RETRY_RECONNECT;
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
        $this->persona->onClose($code, $reason);
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
        $this->tLog(LogLevel::NOTICE, "Roster sync");

        $strategy = $command->strategy;
        $phase = $strategy->getPhase();
        $this->tLog(LogLevel::NOTICE, " sync phase: {phase}", [
            'phase' => $phase
        ]);

        switch ($phase) {
            case 'purge':
                $roster->purge();
                break;

            case 'channels':

                // Get public and private channels

                $scopes = [
                    'public' => $this->web->channels_list()->getBody()['channels'] ?? [],
                    'private' => $this->web->groups_list()->getBody()['channels'] ?? []
                ];
                foreach ($scopes as $scope => $channels) {
                    $this->tLog(LogLevel::INFO, " Indexing {scope} channels", [
                        'scope' => $scope
                    ]);
                    foreach ($channels as $channel) {
                        $this->tLog(LogLevel::INFO, " Channel: {id}:{name}", [
                            'id' => $channel['id'],
                            'name' => $channel['name']
                        ]);
                        $roomObject = new Room($channel['id'], $channel['name']);
                        $roomObject->setTopic($channel['purpose']['value'] ?? "");
                        $roomObject->setData($channel);
                        $roster->map($roomObject);
                    }
                }
                break;

            case 'users':

                // Get users

                $users = $this->web->users_list()->getBody()['members'] ?? [];
                foreach ($users as $user) {
                    $this->tLog(LogLevel::INFO, " User: {id}:{name}", [
                        'id' => $user['id'],
                        'name' => $user['name']
                    ]);
                    $userObject = new User($user['id'], $user['name']);
                    $userObject->setReal($user['real_name'] ?? "");
                    $userObject->setData($user);
                    $roster->map($userObject);
                }
                break;

            default:
                return;
                break;
        }

        // Queue up next phase
        $command->strategy->nextPhase();
        $this->queueCommand($command);
    }

}