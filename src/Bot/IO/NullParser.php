<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;

/**
 * Null Parser
 *
 * This parser sets the command to 'ready' whenever it receives input.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class NullParser extends AbstractParser {

    /**
     * Parse ingested message
     *
     * @param CommandInterface $command
     * @param MessageWrapper $message
     * @return ParserResponse
     * @throws \Exception
     */
    public function parse(CommandInterface $command, MessageWrapper $message): ParserResponse {
        $response = new ParserResponse;
        return $response;
    }

}