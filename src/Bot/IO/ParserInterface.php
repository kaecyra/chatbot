<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\ChatBot\Bot\Command\CommandInterface;

/**
 * Parser interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface ParserInterface {

    public function parse(CommandInterface $command, MessageWrapper $message): ParserResponse;

}