<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

/**
 * Message Wrapper
 *
 * Wrapper for inbound messages from the RTM stream.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
class MessageWrapper {

    /**
     * Parser create time
     * @var int
     */
    protected $createTime;

    /**
     * Message
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $pieces;

    /**
     * Constructor
     *
     * @param string $message
     */
    public function __construct(string $message) {
        $this->createTime = time();
        $this->setMessage($message);
    }

    /**
     * Get create time
     *
     * @return int
     */
    public function getCreateTime(): int {
        return $this->createTime;
    }

    /**
     * Get message string
     *
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }

    /**
     * Set message string
     *
     * @param string $message
     * @return MessageWrapper
     */
    public function setMessage(string $message): MessageWrapper {
        $this->message = $message;
        $this->pieces = explode(' ', $message);
        return $this;
    }

    /**
     * Test whether message === test
     *
     * @param string $test
     * @return bool
     */
    public function is(string $test): bool {
        return ($this->message === $test);
    }

    /**
     * Test if the supplied message is one of the following
     *
     * @param array $opts
     * @return bool
     */
    public function oneof(array $opts): bool {
        foreach ($opts as $opt) {
            if ($this->is($opt)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Test possible phrase on message string
     *
     * @param string $test
     * @param boolean $caseSensitive optional. case sensitive. default false
     * @return boolean
     */
    public function match($test, $caseSensitive = false): bool {
        $flags = '';
        if (!$caseSensitive) {
            $flags = 'i';
        }
        //$test = preg_quote($test, '`');
        return (boolean)preg_match("`{$test}`{$flags}", $this->message);
    }

    /**
     * Test if wrapper has token(s)
     *
     * @param array|string $tokens
     * @param boolean $all optional. require all tokens. default false (any token)
     * @return bool
     */
    public function have($tokens, $all = false): bool {
        if (!is_array($this->pieces) || !count($this->pieces)) {
            return false;
        }

        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }

        foreach ($tokens as $token) {
            if (in_array($token, $this->pieces)) {
                if (!$all) {
                    return true;
                }
            } else {
                if ($all) {
                    return false;
                }
            }
        }

        // If we get here with $all, we have all tokens
        if ($all) {
            return true;
        }

        // If we get here, we don't have all and we got no tokens
        return false;
    }

}