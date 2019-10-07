<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

use Kaecyra\ChatBot\Bot\IO\MessageWrapper;
use Kaecyra\ChatBot\Bot\IO\ParserInterface;
use Kaecyra\ChatBot\Bot\IO\ParserResponse;
use Kaecyra\ChatBot\Bot\Persona;
use Kaecyra\ChatBot\Client\ClientInterface;
use Psr\Container\ContainerInterface;

/**
 * Interactive command parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class InteractiveCommand extends AbstractUserCommand {

    /**
     * Persona
     * @var Persona
     */
    protected $persona;

    /**
     * Client
     * @var ClientInterface
     */
    protected $client;

    /**
     * Array of received messages
     * @var array<MessageWrapper>
     */
    protected $messages;

    /**
     * Parser
     * @var ParserInterface
     */
    protected $parser;

    /**
     * Array of parsed messages
     * @var array<ParserInterface>
     */
    protected $parsed;

    /**
     * InteractiveCommand constructor.
     *
     * @param ContainerInterface $container
     * @param UserDestination $ud
     */
    public function __construct(ContainerInterface $container, ClientInterface $client, UserDestination $ud, Persona $persona) {
        parent::__construct($container);
        $this->messages = [];
        $this->parsed = [];

        $this->persona = $persona;
        $this->client = $client;

        $this->setUserDestination($ud);
    }

    /**
     * Get parser instance
     *
     * @return ParserInterface
     */
    public function getParser(): ParserInterface {
        return $this->parser;
    }

    /**
     * Set parser
     *
     * @param ParserInterface $parser
     * @return InteractiveCommand
     */
    public function setParser(ParserInterface $parser): InteractiveCommand {
        $this->parser = $parser;
        return $this;
    }

    /**
     * Ingest a TextParser
     *
     * @param MessageWrapper $messageWrapper
     * @return ParserResponse
     */
    public function ingestMessage(MessageWrapper $messageWrapper): ParserResponse {
        // Parse text line within context of command
        $this->messages[] = $messageWrapper;
        $response = $this->parser->parse($this, $messageWrapper);
        return $response;
    }

    /**
     * Send an addressed message to a User at a Destination
     *
     * @param string $message
     * @return CommandResponse
     */
    public function sendAddressed(string $message): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedMessage(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $message
        ));
        return $this->getResponseOK();
    }

    /**
     * Send an addressed acknowledge message to a User at a Destination
     *
     * @param string $acknowledge
     * @return CommandResponse
     */
    public function sendAcknowledge(string $acknowledge): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedAcknowledge(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $acknowledge
        ));
        return $this->getResponseRequeue();
    }

    /**
     * Send an addressed complete message to a User at a Destination
     *
     * @param string $complete
     * @return CommandResponse
     */
    public function sendComplete(string $complete): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedComplete(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $complete
        ));
        return $this->getResponseOK();
    }

    /**
     * Send an addressed error message to a User at a Destination
     *
     * @param string $error
     * @return CommandResponse
     */
    public function sendError(string $error): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedError(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $error
        ));
        return $this->getResponseError();
    }

    /**
     * Send an addressed confirmation message to a User at a Destination
     *
     * @param string $confirm
     * @return CommandResponse
     */
    public function sendConfirm(string $confirm): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedConfirm(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $confirm
        ));
        return $this->getResponseRequeue();
    }

    /**
     * Send an addressed delay message to a User at a Destination
     *
     * @param string $delay
     * @return CommandResponse
     */
    public function sendDelay(string $delay): CommandResponse {
        $this->client->sendChat($this->getUserDestination()->getDestination(), $this->persona->getAddressedDelayMessage(
            $this->getUserDestination()->getUser(),
            $this->getUserDestination()->getDestination(),
            $delay
        ));
        return $this->getResponseRequeue();
    }

}
