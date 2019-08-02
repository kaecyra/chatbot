<?php

namespace Kaecyra\ChatBot\Client\Slack;

/**
 * Interface WebClientAwareInterface
 * @package Kaecyra\ChatBot\Client\Slack
 */
interface WebClientAwareInterface {
    function getWebClient(): SlackWebClient;
    function setWebClient(SlackWebClient $webClient);
}
