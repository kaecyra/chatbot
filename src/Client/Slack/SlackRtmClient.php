<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use Kaecyra\ChatBot\Client\ClientInterface;

use Kaecyra\ChatBot\Socket\SocketClient;

use Psr\Log\LogLevel;

use React\EventLoop\LoopInterface;

use Exception;

/**
 * Slack RTM Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SlackRtmClient extends SocketClient implements ClientInterface {

    /**
     * Slack Web Client API
     * @var SlackWebClient
     */
    protected $webClient;

    /**
     * Self identity information
     * @var array
     */
    protected $identity;

    /**
     * Team information
     * @var array
     */
    protected $team;


    public function __construct(LoopInterface $loop, array $settings) {
        parent::__construct($loop, $settings);

        echo "RTM CLIENT CREATED!!\n";

        $this->setMessageFactory(function(){
            return new \Kaecyra\ChatBot\Client\Slack\SocketMessage;
        });
    }

    /**
     * Initialize websocket connection
     *
     * @param SlackWebClient $client
     */
    public function initialize(SlackWebClient $client) {
        $this->tLog(LogLevel::NOTICE, "Initializing RTM API ({hash})", [
            'hash' => spl_object_hash($this)
        ]);

        ob_start();
        debug_print_backtrace();
        $bt = ob_get_clean();

        foreach (explode("\n", $bt) as $traceLine) {
            $this->log(LogLevel::DEBUG, $traceLine);
        }

        $this->webClient = $client;
        $this->webClient->initialize($this->settings['web']['api'], $this->settings['token']);

        try {
            $this->tLog(LogLevel::INFO, " request rtm session");
            $session = $this->webClient->rtm_connect();
            $session = $session->getBody();

            $this->setIdentity($session['self']);
            $this->setTeam($session['team']);
            $this->setDSN($session['url']);

            $this->tLog(LogLevel::INFO, " received rtm session");

            $this->setState(self::STATE_CONFIGURED);
        } catch (Exception $ex) {
            $this->tLog(LogLevel::ERROR, " failed to generate new rtm session: {error}", [
                'error' => $ex->getMessage()
            ]);
        }
    }

    /**
     * Set/Update self identity
     *
     * @param array $identity
     * @param bool $merge
     * @return SlackRtmClient
     */
    public function setIdentity(array $identity, bool $merge = true): SlackRtmClient {
        $this->identity = $merge ? array_merge((array)$this->identity, $identity) : $identity;
        return $this;
    }

    /**
     * Get self identity
     *
     * @return array
     */
    public function getIdentity(): array {
        return (array)$this->identity;
    }

    /**
     * Set/Update team info
     *
     * @param array $team
     * @param bool $merge
     * @return SlackRtmClient
     */
    public function setTeam(array $team, bool $merge = true): SlackRtmClient {
        $this->team = $merge ? array_merge((array)$this->team, $team) : $team;
        return $this;
    }

    /**
     * Get team info
     *
     * @return array
     */
    public function getTeam(): array {
        return (array)$this->team;
    }

    /**
     * Tick
     *
     */
    public function tick() {
        return;
    }

}