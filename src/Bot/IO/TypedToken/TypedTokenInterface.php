<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Typed Token Interface
 *
 * @author Francis Caisse <francis.c@vanillaforums.com>
 * @version 1.0
 */
interface TypedTokenInterface {

    /**
     * Validate inputted token
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     *
     * @return mixed
     * @throws TokenFormatException
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema);

    /**
     * Flush any internal caches
     *
     * @return mixed
     */
    public function flush();

    /**
     * Return a format token
     *
     * Instead of returning "date", return "date:YYYY-MM-DD"
     *
     * @return string
     */
    public function getFormatToken(): string;

    /**
     * Return an example token
     *
     * Instead of returning "date" or "YYYY-MM-DD", return "1970-01-01"
     *
     * @return string
     */
    public function getExample(): string;

}
