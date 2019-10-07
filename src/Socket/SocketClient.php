<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Socket;

use Exception;
use Kaecyra\ChatBot\Client\AbstractClient;
use Kaecyra\ChatBot\Client\ClientInterface;
use Psr\Log\LogLevel;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface as SocketMessageInterface;
use React\EventLoop\LoopInterface;

/**
 * Socket Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class SocketClient extends AbstractClient {

    const RETRY_INIT = -2;
    const RETRY_RECONNECT = -1;
    const RETRY_CONNECT = 0;
    const RETRY_DELAY = 15;

    const MESSAGE_INBOUND = 'inbound';
    const MESSAGE_OUTBOUND = 'outbound';

    /**
     * Server info
     * @var array
     */
    protected $server;

    /**
     * Retry delay
     * @var integer
     */
    protected $retry;

    /**
     * WebSocket connection
     * @var WebSocket
     */
    protected $connection;

    /**
     * Tick frequency
     * @var float
     */
    protected $tickFreq = 1.0;

    /**
     * Class for message handler
     * @var callable
     */
    protected $messageFactory;

    /**
     * Connection DSN
     * @var string
     */
    protected $dsn = '';

    /**
     *
     * @param LoopInterface $loop
     * @param array $settings
     */
    public function __construct(LoopInterface $loop, array $settings) {
        parent::__construct($settings);

        $this->loop = $loop;
        $this->retry = self::RETRY_INIT;
    }

    /**
     * Run client
     *
     */
    public function run() {
        $this->tLog(LogLevel::NOTICE, "Running socket client");
        if (!$this->isState(ClientInterface::STATE_CONFIGURED)) {
            $this->tLog(LogLevel::ERROR, " not configured");
            return;
        }

        $this->loop->addPeriodicTimer($this->tickFreq, [$this, 'tick']);
        $this->loop->run();
    }

    /**
     * Connection ticker
     *
     */
    public function tick() {
        $this->tLog(LogLevel::DEBUG, "tick");

        // Stay connected
        if (!($this->connection instanceof WebSocket)) {
            $this->tLog(LogLevel::DEBUG, "[currently] not connected");

            if ($this->retry == self::RETRY_INIT) {
                $this->retry = self::RETRY_CONNECT;
                $this->tLog(LogLevel::DEBUG, " initial connect");
            }

            if ($this->retry == self::RETRY_RECONNECT) {
                $this->retry = self::RETRY_DELAY;
                $this->tLog(LogLevel::WARNING, " retrying in {$this->retry} sec");
                return;
            }

            if ($this->retry == self::RETRY_CONNECT) {

                $this->connect();

            } else {
                $this->retry--;
            }

            return false;
        } else {
            $this->tLog(LogLevel::DEBUG, "[currently] connected");
        }

        //
    }

    /**
     * Set connection DSN
     *
     * @param string $dsn
     * @return SocketClient
     */
    public function setDSN(string $dsn): SocketClient {
        $this->dsn = $dsn;
        return $this;
    }

    /**
     * Get connection DSN
     *
     * @return string
     */
    public function getDSN(): string {
        return $this->dsn;
    }

    /**
     * Set SocketMessage factory callable
     *
     * @param callable $factory
     * @return SocketClient
     */
    public function setMessageFactory(callable $factory): SocketClient {
        $this->messageFactory = $factory;
        return $this;
    }

    /**
     * Get fresh socket message
     *
     * @param string $direction 'inbound' or 'outbound'
     * @return SocketMessageInterface
     * @throws Exception
     */
    public function getMessage(string $direction): MessageInterface {
        if (is_callable($this->messageFactory)) {
            return call_user_func($this->messageFactory, $direction);
        }
        throw new Exception('Failed to create socket message, factory not configured');
    }

    /**
     * Connect and maintain
     *
     * @param string $connectionDSN
     */
    public function connect(string $connectionDSN = null) {
        if (!is_null($connectionDSN)) {
            $this->setDSN($connectionDSN);
        }

        // Check internet
        if (!$this->haveConnectivity()) {
            $this->retry = self::RETRY_RECONNECT;
            $this->tLog(LogLevel::WARNING, "No internet connection");
            return;
        }

        $this->retry = self::RETRY_RECONNECT;
        $this->tLog(LogLevel::NOTICE, " connecting socket client: {dsn}", [
            'dsn' => $this->getDSN()
        ]);

        $this->setState(ClientInterface::STATE_CONNECTING);

        $connector = new \Ratchet\Client\Connector($this->loop);
        $connector($this->getDSN(), [], [

        ])->then([$this, 'connectSuccess'], [$this, 'connectFailed']);
    }

    /**
     * Connected to socket server
     *
     * @param WebSocket $connection
     */
    public function connectSuccess(WebSocket $connection) {
        $this->tLog(LogLevel::NOTICE, "connected");

        $this->connection = $connection;
        $this->connection->on('message', [$this, 'onSocketMessage']);
        $this->connection->on('close', [$this, 'onSocketClose']);
        $this->connection->on('error', [$this, 'onSocketError']);

        $this->setState(ClientInterface::STATE_CONNECTED);

        $this->fire('socket_connected');
    }

    /**
     * Handle connection failure
     *
     * @param Exception $ex
     */
    public function connectFailed(Exception $ex) {
        $this->tLog(LogLevel::ERROR, "could not connect: {$ex->getMessage()}");
        $this->offline();

        $this->fire('socket_failed');
    }

    /**
     * Test internet connectivity
     *
     * @return boolean
     */
    public function haveConnectivity() {
        $connected = @fsockopen("www.google.com", 80);
        if ($connected) {
            $is_conn = true;
            fclose($connected);
        }else{
            $is_conn = false;
        }
        return $is_conn;
    }

    /**
     *
     */
    public function reconnect() {
        $this->tLog(LogLevel::NOTICE, "Reconnecting");
        $this->connection->close();
        $this->connect();
    }

    /**
     * Client offline
     *
     */
    public function offline() {
        $this->connection = null;
        $this->setState(ClientInterface::STATE_OFFLINE);
    }

    /**
     * Send a message to the server
     *
     * @param string $method
     * @param array $data
     */
    public function sendMessage(string $method, array $data = []) {
        $this->tLog(LogLevel::DEBUG, "send socket message: {$method}");

        $message = $this->getMessage(self::MESSAGE_OUTBOUND);
        $message->populate($method, $data);

        $this->connection->send($message);
    }

    /**
     * Handle receiving socket message
     *
     * @param SocketMessageInterface $socketMessage
     */
    public function onSocketMessage(SocketMessageInterface $socketMessage) {
        $this->tLog(LogLevel::DEBUG, "socket message received");

        try {
            $message = $this->getMessage(self::MESSAGE_INBOUND);
            $message->ingest($socketMessage);

            $this->tLog(LogLevel::DEBUG, "socket message method: ".$message->getMethod());

            // Route to message handler
            $this->fire('socket_message', [$message]);
            $this->onMessage($message);

        } catch (\Exception $ex) {
            $this->tLog(LogLevel::ERROR, "socket message handling error: ".$ex->getMessage());
            return false;
        }
    }

    /**
     * Handle connection closing
     *
     * @param int $code
     * @param string $reason
     */
    public function onSocketClose($code = null, $reason = null) {
        $this->tLog(LogLevel::NOTICE, "socket connection closed ({code} - {reason})", [
            'code' => $code ?? 'unknown code',
            'reason' => $reason ?? 'unknown reason'
        ]);
        $this->offline();

        // Propagate disconnect event
        $this->fire('socket_closed');
        $this->onClose($code, $reason);
    }

    /**
     * Handle socket errors
     *
     * @param string $reason
     * @param WebSocket $connection
     */
    public function onSocketError(string $reason, WebSocket $connection) {
        $this->tLog(LogLevel::ERROR, "socket error: {reason}", [
            'reason' => $reason ?? 'unknown reason'
        ]);

        // Propagate error event
        $this->fire('socket_error');
        $this->onError(null, $reason);
    }

}