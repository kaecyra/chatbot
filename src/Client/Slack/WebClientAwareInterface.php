<?php

namespace Kaecyra\ChatBot\Client\Slack;

/**
 * Interface WebClientAwareInterface
 * @package Kaecyra\ChatBot\Client\Slack
 */
interface WebClientAwareInterface {

    public function getWebClient(): SlackWebClient;

    public function setWebClient(SlackWebClient $webClient);
}
