<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Future Date Token
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 1.0
 */
class FutureDate extends Date {

    /**
     * Validate a parameter as a future date
     *
     * Format should be YYYY-mm-dd, and in the future
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     * @return bool
     * @throws \Exception
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema) {
        $valid = parent::validateToken($token, $prevToken, $tokenSchema);
        if ($valid === false) {
            return $valid;
        }

        $now = date('Y-m-d');

        $nowTs = strtotime($now);
        $tokenTs = strtotime($token);

        if ($nowTs > $tokenTs) {
            $exception = new TokenFormatException("%s is not in the future.");
            $exception->setToken($token);
            throw $exception;
        }

        return $token;
    }
}