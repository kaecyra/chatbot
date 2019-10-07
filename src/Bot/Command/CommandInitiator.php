<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Command;

use Kaecyra\ChatBot\Bot\IO\ParserInterface;
use Kaecyra\ChatBot\Bot\Strategy\AbstractStrategy;

/**
 * Command Initiator
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class CommandInitiator {

    /**
     * Command
     * @var string
     */
    protected $command;

    /**
     * Command handler
     * @var callable
     */
    protected $commandHandler;

    /**
     * Access roles
     * @var array
     */
    protected $roles;

    /**
     * Parser
     * @var string
     */
    protected $parser;
    /**
     * Parser data
     * @var array
     */
    protected $parserData;

    /**
     * Command strategy
     * @var AbstractStrategy
     */
    protected $strategy;

    /**
     * CommandInitiator constructor.
     *
     * @param string $command
     * @param callable $handler
     * @param array $roles
     * @param string $parser
     * @param array $parserData
     * @throws \Exception
     */
    public function __construct(
        string $command,
        callable $handler,
        array $roles,
        AbstractStrategy $strategy,
        string $parser = null,
        array $parserData = []
    ) {
        $this->setCommand($command);
        $this->setCommandHandler($handler);
        $this->setRoles($roles);
        $this->setStrategy($strategy);
        $this->setParser($parser);
        $this->setParserData($parserData);
    }

    /**
     * Set triggered command
     *
     * @param string $command
     * @return CommandInitiator
     */
    public function setCommand(string $command): CommandInitiator {
        $this->command = $command;
        return $this;
    }

    /**
     * Get triggered command
     *
     * @return string
     */
    public function getCommand(): string {
        return $this->command;
    }

    /**
     * Set command handler
     *
     * @param callable $handler
     * @return CommandInitiator
     * @throws \Exception
     */
    public function setCommandHandler(callable $handler): CommandInitiator {
        if (!is_callable($handler)) {
            throw new \Exception("Supplied command handler is not callable.");
        }
        $this->commandHandler = $handler;
        return $this;
    }

    /**
     * Get command handler
     *
     * @return callable
     */
    public function getCommandHandler(): callable {
        return $this->commandHandler;
    }

    /**
     * Get access roles
     * @return array
     */
    public function getRoles(): array {
        return $this->roles;
    }

    /**
     * Set access roles
     * @param array $roles
     * @return CommandInitiator
     */
    public function setRoles(array $roles) {
        $this->roles = $roles;
        return $this;
    }

    /**
     * Get command strategy
     * @return AbstractStrategy
     */
    public function getStrategy(): AbstractStrategy {
        return $this->strategy;
    }

    /**
     * Set command strategy
     * @param AbstractStrategy $strategy
     * @return CommandInitiator
     */
    public function setStrategy(AbstractStrategy $strategy) {
        $this->strategy = $strategy;
        return $this;
    }

    /**
     * Set parser
     *
     * @param string $parser
     * @return CommandInitiator
     * @throws \Exception
     */
    public function setParser(string $parser): CommandInitiator {
        if (!class_exists($parser)) {
            throw new \Exception("Supplied parser {$parser} does not exist.");
        }

        $parserReflect = new \ReflectionClass($parser);
        if (!$parserReflect->implementsInterface(ParserInterface::class)) {
            throw new \Exception("Supplied parser {$parser} is not a valid parser.");
        }

        $this->parser = $parser;
        return $this;
    }

    /**
     * Get parser
     *
     * @return string
     */
    public function getParser(): string {
        return $this->parser;
    }

    /**
     * Set supplemental parser data
     *
     * @param array $parserData
     * @return CommandInitiator
     */
    public function setParserData(array $parserData): CommandInitiator {
        $this->parserData = $parserData;
        return $this;
    }

    /**
     * Get supplemental parser data
     *
     * @return array
     */
    public function getParserData(): array {
        return $this->parserData;
    }

}