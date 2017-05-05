<?php

/**
 * @license MIT
 * @copyright 2016-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Client\Slack;

use Garden\Http\HttpClient;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use \Exception;

/**
 * Slack Web Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SlackWebClient extends HttpClient implements LoggerAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;

    /**
     * API token
     * @var string
     */
    protected $token;


    public function __construct($baseUrl = '') {
        parent::__construct($baseUrl);

        $this->tLog(LogLevel::DEBUG, "Created slack web client instance");
    }

    /**
     * Initialize web client
     *
     * @param string $baseUrl
     * @param string $token
     */
    public function initialize(string $baseUrl, string $token) {
        $this->tLog(LogLevel::NOTICE, "Initializing Web API");

        $this->setBaseUrl($baseUrl);
        $this->tLog(LogLevel::INFO, " url: {baseurl}", [
            'baseurl' => $baseUrl
        ]);

        $this->token = $token;
        $this->tLog(LogLevel::INFO, " token: {token}", [
            'token' => substr($token, 0, 5).str_repeat('*', strlen($token)-10).substr($token, -5)
        ]);

        $this
            ->setDefaultHeader('Content-Type', 'application/json; charset=utf8')
            ->setDefaultHeader('Accept', 'application/json');
    }

    /**
     * Passthru method for GET requests
     *
     * @param string $uri
     * @param array $query
     * @param array $headers
     * @param array $options
     * @return mixed
     */
    public function get($uri, array $query = array(), array $headers = array(), $options = array()) {
        try {
            $query['token'] = $this->token;
            $r = parent::get($uri, $query, $headers, $options);
        } catch (\Exception $ex) {
            throw $ex;
        }

        if ($r['ok'] === false) {
            $this->tLog(LogLevel::ERROR, $r['error'], $r->getBody());
            throw new Exception($r['error']);
        }

        if (($r['warning'] ?? false) !== false) {
            $this->tLog(LogLevel::WARNING, $r['warning'], $r->getBody());
        }

        return $r;
    }

    /**
     * Passthru method for POST requests
     *
     * @param string $uri
     * @param mixed $body
     * @param array $headers
     * @param array $options
     * @return mixed
     */
    public function post($uri, $body = array(), array $headers = array(), $options = array()) {
        try {
            $body['token'] = $this->token;
            $r = parent::post($uri, $body, $headers, $options);
        } catch (\Exception $ex) {
            throw $ex;
        }

        if ($r['ok'] === false) {
            $this->tLog(LogLevel::ERROR, $r['error'], $r->getBody());
            throw new Exception($r['error']);
        }

        if (($r['warning'] ?? false) !== false) {
            $this->tLog(LogLevel::WARNING, $r['warning'], $r->getBody());
        }

        return $r;
    }

    /**
     * Connect to RTM session
     *
     * Use the internal token to request a new RTM session.
     */
    public function rtm_connect() {
        return $this->get('/rtm.connect');
    }



}