<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;
use Kaecyra\ChatBot\Bot\IO\TypedToken\AbstractTypedToken;
use Kaecyra\ChatBot\Bot\IO\TypedToken\TokenFormatException;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;

/**
 * Schema Parser
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class SchemaParser extends AbstractParser {

    /**
     * Command schema
     * @var array
     */
    protected $schema;

    /**
     * Number of parses
     * @var int
     */
    protected $parses;

    /**
     * SchemaParser constructor.
     *
     * @param ContainerInterface $container
     * @param array $schema
     */
    public function __construct(ContainerInterface $container, array $schema) {
        parent::__construct($container);
        $this->setSchema($schema);
        $this->parses = 0;
    }

    /**
     * Set schema
     *
     * @param array $schema
     * @return SchemaParser
     */
    public function setSchema(array $schema): SchemaParser {
        $this->schema = $schema;

        if (!array_key_exists('exactTokens', $this->schema)) {
            $this->schema['exactTokens'] = [];
        }

        if (!array_key_exists('typedTokens', $this->schema)) {
            $this->schema['typedTokens'] = [];
        }
        return $this;
    }

    /**
     * Get current schema
     *
     * @return array
     */
    public function getSchema(): array {
        return $this->schema ?? [];
    }

    /**
     * Parse ingested message
     *
     * @param CommandInterface $command
     * @param MessageWrapper $message
     * @return ParserResponse
     * @throws \Exception
     */
    public function parse(CommandInterface $command, MessageWrapper $message): ParserResponse {
        $this->parses++;
        $this->tLog(LogLevel::INFO, "Parsing inbound message ({message})[{parses}] for {command}/{guid}", [
            'message' => $message->getMessage(),
            'parses' => $this->parses,
            'command' => $command->getCommand(),
            'guid' => $command->getGuid()
        ]);

        $commandString = trim($message->getMessage());
        $exemplars = $this->getExemplars();

        $response = new ParserResponse;

        // Allow cancels
        if ($message->oneof(['no', 'no thanks', 'nevermind', 'stop', 'oops', 'abort', 'cancel', 'forget it', 'forget about it'])) {
            $response->setStatus(ParserResponse::STATUS_CANCEL);
            return $response;
        }

        // Wait for confirm/deny
        if ($command->isReady() && $command->isWaiting()) {
            if ($message->oneof(['yes', 'yes please', 'yup', 'ok', 'go', 'engage', 'excelsior', 'cool', 'lets get it', 'make it happen', 'make it so', 'roll it'])) {
                $response->setStatus(ParserResponse::STATUS_OK);
            } else {
                $response->setStatus(ParserResponse::STATUS_OK_CONFIRM);
            }
            return $response;
        }

        // On first parse, check presence of exact tokens (if defined)
        if ($this->parses === 1) {
            // Validate exact tokens (they should all be there)
            $valid = $this->validateExactTokens($commandString);

            // If any remain, the command is invalid and we directly fail out without any continue
            if (!$valid) {
                $response->addError("Badly formatted %s.", [
                    $command->getCommand()
                ]);
                $response->addError("I expected something like: {$exemplars['format']}");
                return $response;
            }
        }

        // Check for arguments/flags
        if (isset($this->schema['flags'])) {
            foreach ($this->schema['flags'] as $flag) {
                if ($message->have("--{$flag}")) {
                    $command->addTarget($flag, true);
                }
            }
        }

        // Reduce double-space to single-space to protect explode()
        $commandString = preg_replace("~  +~", ' ', $commandString);

        $commandTokens = explode(' ', $commandString);
        $commandTokens = $this->parseTypedTokens($command, $response, $commandTokens);

        /*
         * If we make it here and:
         *  1. remaining typed schema tokens yet to be found
         *  2. tokens from the commands that weren't matched
         *
         * Then either the user made a mistake (i.e. a typo) or our cache is old and something new was added (repo, cluster, etc).
         * Clear the cache and re-parse.
         */
        if (!$this->isSatisfied() && !empty($commandTokens)) {
            $commandTokens = $this->parseTypedTokens($command, $response, $commandTokens, true);
        }

        if (!$this->isSatisfied()) {

            // Get existing typed token errors from response, then clear them
            $existingErrors = $response->getErrors();
            $response->clearErrors();

            $response->addError("Working on that %s for you.", [
                $command->getCommand()
            ]);
            $response->addError("I expected something like: {$exemplars['format']}");

            $missing = [];
            foreach ($this->schema['typedTokens'] as $tokenIndex => $tokenSchema) {
                if ($tokenSchema['satisfied'] !== true) {
                    $missing[] = $tokenIndex;
                }
            }
            $toks = count($missing) > 1 ? "the " . implode(", ", array_fill(0, count($missing) -1, "%s")) . " and %s" : "the %s";
            $response->addError("Please give me {$toks}", $missing);

            // Re-add typed token errors to response
            $response->addBulkErrors($existingErrors);
        }

        // Request confirmation of commands that are completed in more than one parse
        if ($response->getStatus() == ParserResponse::STATUS_OK && $this->parses > 1) {
            $response->setStatus(ParserResponse::STATUS_OK_CONFIRM);
        }

        if ($response->getStatus() == ParserResponse::STATUS_ERROR) {
            $response->setStatus(ParserResponse::STATUS_CONTINUE);
        }

        return $response;
    }

    /**
     * Validate command string's conformance to exactTokens
     *
     * @param string $commandString
     * @return bool
     */
    protected function validateExactTokens(string $commandString): bool {
        $i = 0;

        // Iterate over all known schema exact tokens
        foreach ($this->schema['exactTokens'] as $schemaIndex => $schemaToken) {
            if (preg_match("/{$schemaToken}/", $commandString)) {
                $i++;
                $commandString = trim(preg_replace("~\s?{$schemaToken}~i", '', $commandString, 1));
            }
        }
        return $i == count($this->schema['exactTokens']);
    }

    /**
     * Parse message for typed tokens
     *
     * Iterate over message by token and attempt to match with expected typed tokens in schema.
     *
     * @param CommandInterface $command
     * @param ParserResponse $response
     * @param array $commandTokens
     * @param bool $bustCache
     * @return array
     */
    protected function parseTypedTokens(CommandInterface $command, ParserResponse $response, array $commandTokens, $bustCache = false): array {
        // Iterate over all known schema TypedTokens
        foreach ($this->schema['typedTokens'] as $schemaIndex => &$tokenSchema) {
            $typedTokens = [];

            if (is_array($tokenSchema['type'])) {
                foreach ($tokenSchema['type'] as $type) {
                    $typedTokens[] = $this->container->get($type);
                }
            } else {
                $typedTokens[] = $this->container->get($tokenSchema['type']);
            }

            /** @var AbstractTypedToken $typedToken */
            foreach ($typedTokens as $typedToken) {
                if ($bustCache) {
                    $typedToken->flush();
                }

                // Iterate over input tokens
                $prevToken = null;
                foreach ($commandTokens as $tokenIndex => $commandToken) {
                    try {

                        // Test input token against current TypedToken
                        $valid = $typedToken->validateToken($commandToken, $prevToken, $tokenSchema);
                        if ($valid !== false) {
                            $this->tLog(LogLevel::INFO, "Matched TypedToken {typed} -> {value}", [
                                'typed' => $schemaIndex,
                                'value' => $valid
                            ]);
                            $tokenSchema['satisfied'] = true;
                            unset($commandTokens[$tokenIndex]);
                            $command->addTarget($schemaIndex, $valid);
                        }
                    } catch (TokenFormatException $e) {

                        // Only send errors and discard tokens when the cache is being busted (round 2)
                        if ($bustCache) {
                            $response->addError($e->getMessage(), [
                                $e->getToken()
                            ]);
                            unset($commandTokens[$tokenIndex]);
                        }
                    }
                    $prevToken = $commandToken;
                }
            }
        }

        return $commandTokens;
    }

    /**
     * Check if schema is satisfied
     *
     * @return bool
     */
    public function isSatisfied(): bool {
        foreach ($this->schema['typedTokens'] as $tokenSchema) {
            if (($tokenSchema['satisfied'] ?? false) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return exemplar commands
     * [
     *   'format' => ''
     *   'example' => ''
     * ]
     * @return array
     */
    public function getExemplars(): array {
        $commandStr = $this->schema['command'];
        preg_match_all('/\{([\w\s\d]+)}/', $commandStr, $matches);

        $exemplars = [
            'format' => $commandStr,
            'example' => $commandStr
        ];

        foreach ($matches[1] as $tokenIndex) {
            if (!array_key_exists($tokenIndex, $this->schema['typedTokens'])) {
                continue;
            }
            $tokenSchema = $this->schema['typedTokens'][$tokenIndex];
            $typedTokens = [];

            if (is_array($tokenSchema['type'])) {
                foreach ($tokenSchema['type'] as $type) {
                    $typedTokens[] = $this->container->get($type);
                }

                $example = [];
                foreach ($typedTokens as $typedToken) {
                    $example[] = $typedToken->getExample();
                }

                $example = implode(' or ', $example);
            } else {
                $typedTokens[] = $this->container->get($tokenSchema['type']);
                $example = $typedTokens[0]->getExample();
            }

            $exemplars['format'] = str_replace("{{$tokenIndex}}", "{{$tokenIndex}:$example}", $exemplars['format']);
            $exemplars['example'] = str_replace("{{$tokenIndex}}", $example, $exemplars['example']);
        }

        return $exemplars;
    }

    /**
     * Get finalized command with arguments back-replaced
     *
     * @param CommandInterface $command
     * @return string
     */
    public function getFinal(CommandInterface $command): string {
        $commandStr = $this->schema['command'];
        preg_match_all('/\{([\w\s\d]+)}/', $commandStr, $matches);
        foreach ($matches[1] as $tokenIndex) {
            if (!array_key_exists($tokenIndex, $this->schema['typedTokens'])) {
                continue;
            }
            $commandStr = str_replace("{{$tokenIndex}}", $command->getTarget($tokenIndex), $commandStr);
        }
        return $commandStr;
    }

}