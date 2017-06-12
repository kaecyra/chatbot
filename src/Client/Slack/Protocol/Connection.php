<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack\Protocol;

use Kaecyra\ChatBot\Client\Slack\SlackRtmClient;
use Kaecyra\ChatBot\Socket\MessageInterface;

use Kaecyra\ChatBot\Client\Slack\Strategy\StartupSyncStrategy;

use Kaecyra\ChatBot\Bot\Command\SimpleCommand;

use Psr\Log\LogLevel;

use \Exception;

/**
 * Connection protocol handler
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class Connection extends AbstractProtocolHandler {

    const PING_FREQ = 5;
    const VERIFY_FREQ = 5;
    const RECONNECT_AFTER = 3;

    /**
     * When we last sent a ping
     * @var int
     */
    protected $lastPing;

    /**
     * Last ping that was acknowledged
     * @var int
     */
    protected $lastAcknowledged;

    /**
     * When we last verified pings
     * @var int
     */
    protected $lastVerified;

    /**
     * List of outstanding pings
     * @var array
     */
    protected $pings;

    /**
     *
     * @param SlackRtmClient $client
     */
    public function start(SlackRtmClient $client) {
        $client->addMessageHandler('hello', [$this, 'message_hello']);
        $client->addMessageHandler('goodbye', [$this, 'message_goodbye']);

        $client->addActionHandler('ping', [$this, 'action_ping']);
        $client->addActionHandler('verify_pings', [$this, 'action_verify_pings']);
        $client->addMessageHandler('pong', [$this, 'message_pong']);
    }

    /**
     * Handle 'hello' message
     *
     * Connection is established and server is ready for messages. We should now
     * get synced up.
     *
     * @param SlackRtmClient $client
     * @param MessageInterface $message
     */
    public function message_hello(SlackRtmClient $client, MessageInterface $message) {
        $this->tLog(LogLevel::NOTICE, "Server sent 'hello'. Connected.");
        $this->pings = [];

        // Queue boot sync
        $sync = new SimpleCommand('roster_sync');
        $sync->strategy = new StartupSyncStrategy;
        $client->queueCommand($sync);
    }

    /**
     * Handle 'goodbye' message
     *
     * Server intends to disconnect us, so reconnect. "I'm not fired, I quit!"
     *
     * @param SlackRtmClient $client
     * @param MessageInterface $message
     */
    public function message_goodbye(SlackRtmClient $client, MessageInterface $message) {
        $this->tLog(LogLevel::NOTICE, "Server sent 'goodbye'.");
        $this->tLog(LogLevel::INFO, "I'm not fired, I QUIT!");
        //$client->reconnect();
    }

    /**
     * Routine pings
     *
     * @param SlackRtmClient $client
     */
    public function action_ping(SlackRtmClient $client) {
        if (time() <= ($this->lastPing + self::PING_FREQ)) {
            return;
        }
        $this->lastPing = time();

        $pid = "{$this->lastPing}-".mt_rand(10000,99999);
        $this->pings[$pid] = [
            'pingtime' => $this->lastPing,
            'time' => microtime(true)
        ];
        $client->sendMessage('ping', [
            'pid' => $pid
        ]);
    }

    /**
     * Handle 'pong' message
     *
     * @param MessageInterface $message
     */
    public function message_pong(MessageInterface $message) {
        $data = $message->getData();
        $pid = $data['pid'] ?? null;
        if (!$pid) {
            $this->tLog(LogLevel::WARNING, "Server sent 'pong' without a pid.");
            return;
        }

        if (!array_key_exists($pid, $this->pings)) {
            $this->tLog(LogLevel::WARNING, "Server sent 'pong' with an unknown pid: '{pid}'", [
                'pid' => $pid
            ]);
            return;
        }

        $ping = $this->pings[$pid];

        // Remember the last ping that was acknowledged
        $this->lastAcknowledged = $ping['pingtime'];
        $elapsedSeconds = microtime(true) - $ping['time'];

        if ($elapsedSeconds > 0.1) {
            $elapsedFormat = round($elapsedSeconds, 2).'s';
        } else {
            $elapsedFormat = round($elapsedSeconds*1000,0).'ms';
        }

        unset($this->pings[$pid]);
        $this->tLog(LogLevel::INFO, "Server sent verified 'pong' (took {elapsed}).", [
            'elapsed_raw' => $elapsedSeconds,
            'elapsed' => $elapsedFormat
        ]);
    }

    /**
     * Ping pair cleanup
     *
     * @param SlackRtmClient $client
     */
    public function action_verify_pings(SlackRtmClient $client) {
        if (time() <= ($this->lastVerified + self::VERIFY_FREQ)) {
            return;
        }
        $this->lastVerified = time();

        // First, unset all pings older than the most recent one received
        $pids = array_keys($this->pings);
        foreach ($pids as $pid) {
            $ping = $this->pings[$pid];
            if ($ping['pingtime'] < $this->lastAcknowledged) {
                unset($this->pings[$pid]);
            }
        }

        // Count how many remain
        $outstanding = count($this->pings);

        $this->tLog(LogLevel::DEBUG, "{outstanding} pings are unanswered.", [
            'outstanding' => $outstanding
        ]);

        if ($outstanding <= 1) {
            return;
        }

        // Take action
        if ($outstanding >= self::RECONNECT_AFTER) {
            $this->tLog(LogLevel::ERROR, "Too many missed pings, reconnecting.");
            $client->reconnect();
        } else {
            $this->tLog(LogLevel::WARNING, "Server has not responded to {outstanding} pings.", [
                'outstanding' => $outstanding
            ]);
        }
    }

}