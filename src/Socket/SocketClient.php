<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Socket;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use React\EventLoop\LoopInterface;

use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;

use Kaecyra\ChatBot\Socket\MessageInterface as SocketMessageInterface;

use \Exception;

/**
 * Socket Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class SocketClient implements LoggerAwareInterface, TaggedLogInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;
    use EventAwareTrait;

    const RETRY_INIT = -2;
    const RETRY_RECONNECT = -1;
    const RETRY_CONNECT = 0;

    const RETRY_DELAY = 15;

    const STATE_FRESH = 'fresh';
    const STATE_CONFIGURED = 'configured';
    const STATE_OFFLINE = 'offline';
    const STATE_CONNECTING = 'connecting';
    const STATE_CONNECTED = 'connected';
    const STATE_READY = 'ready';

    /**
     * Client settings
     * @var array
     */
    protected $settings;

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
     * Connection state
     * @var string
     */
    protected $state = self::STATE_FRESH;

    /**
     * Tick frequency
     * @var integer
     */
    protected $tickFreq = 1;

    /**
     * Class for message handler
     * @var callable
     */
    protected $messageFactory;

    /**
     *
     * @param LoopInterface $loop
     * @param array $settings
     */
    public function __construct(LoopInterface $loop, array $settings) {
        $this->loop = $loop;
        $this->retry = self::RETRY_INIT;
        $this->settings = $settings;
    }

    /**
     * Run client
     *
     */
    public function run() {
        if (!$this->isState(self::STATE_CONFIGURED)) {
            $this->tLog(LogLevel::ERROR, "not configured");
            return;
        }

        $this->tLog(LogLevel::NOTICE, "running socket client");
        $this->loop->addPeriodicTimer($this->tickFreq, [$this, 'tick']);
        $this->loop->run();
    }

    /**
     * Connection ticker
     *
     */
    public function tick() {
        // Stay connected
        if (!($this->connection instanceof WebSocket)) {
            if ($this->retry == self::RETRY_INIT) {
                $this->retry = self::RETRY_CONNECT;
                $this->tLog(LogLevel::NOTICE, "connecting");
            }

            if ($this->retry <= self::RETRY_RECONNECT) {
                $this->retry = self::RETRY_DELAY;
                $this->tLog(LogLevel::WARNING, "retrying in {$this->retry} sec");
            }

            if (!$this->retry) {
                $this->connect();
            } else {
                $this->retry--;
            }

            return false;
        }

        return true;
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
     * Set connection state
     *
     * @param string $state
     * @return SocketClient
     */
    public function setState(string $state) {
        $this->state = $state;
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
     * @return SocketMessageInterface
     * @throws Exception
     */
    public function getMessage(): SocketMessageInterface {
        if (is_callable($this->messageFactory)) {
            return call_user_func($this->messageFactory);
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

        $this->retry = self::RETRY_RECONNECT;
        $this->tLog(LogLevel::NOTICE, "connecting: {dsn}", [
            'dsn' => $this->getDSN()
        ]);

        $this->setState(self::STATE_CONNECTING);

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
        $this->connection->on('message', [$this, 'onMessage']);
        $this->connection->on('close', [$this, 'onClose']);
        $this->connection->on('error', [$this, 'onError']);

        $this->setState(self::STATE_CONNECTED);

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
    }

    /**
     * Client offline
     *
     */
    public function offline() {
        $this->connection = null;
        $this->setState(self::STATE_OFFLINE);
    }

    /**
     * Test if client is ready
     *
     * @return boolean
     */
    public function isReady() {
        return $this->isState(self::STATE_READY);
    }

    /**
     * Send a message to the server
     *
     * @param string $method
     * @param mixed $data
     */
    public function sendMessage(string $method, $data = null) {
        $this->tLog(LogLevel::INFO, "send message: {$method}");

        $message = $this->getMessage();
        $message->populate($method, $data);

        $this->connection->send($message);
    }

    /**
     * Handle receiving socket message
     *
     * @param MessageInterface $msg
     */
    public function onMessage(MessageInterface $msg) {
        $this->tLog(LogLevel::INFO, "message received");

        try {
            $message = $this->getMessage();
            $message->parse($msg);

            $this->tLog(LogLevel::INFO, "message method: ".$message->getMethod());

            // Route to message handler
            $call = 'message_'.$message->getMethod();
            if (is_callable([$this, $call])) {
                $this->$call($message);
            } else {
                $this->tLog(LogLevel::WARNING, sprintf(" could not handle message: unknown type '{%s}'", $message->getMethod()));
            }
        } catch (\Exception $ex) {
            $this->tLog(LogLevel::ERROR, "msg handling error: ".$ex->getMessage());
            return false;
        }
    }

    /**
     * Handle connection closing
     *
     * @param integer $code
     * @param string $reason
     */
    public function onClose($code = null, $reason = null) {
        $this->tLog(LogLevel::NOTICE, "connection closed ({$code} - {$reason})");
        $this->offline();
    }

    /**
     *
     * @param string $reason
     * @param WebSocket $connection
     */
    public function onError($reason, WebSocket $connection) {
        $this->tLog(LogLevel::ERROR, "socket error: {$reason}");
    }

}