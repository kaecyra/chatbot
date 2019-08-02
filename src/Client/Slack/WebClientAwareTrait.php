<?php

namespace Kaecyra\ChatBot\Client\Slack;

/**
 * Trait WebClientAwareTrait
 * @package Kaecyra\ChatBot\Client\Slack
 */
trait WebClientAwareTrait {

    /**
     * @var $webClient
     */
    protected $webClient;

    /**
     * @return SlackWebClient
     */
    public function getWebClient(): SlackWebClient {
        return $this->webClient;
    }

    /**
     * @param SlackWebClient $webClient
     */
    public function setWebClient(SlackWebClient $webClient) {
        $this->webClient = $webClient;
    }
}
