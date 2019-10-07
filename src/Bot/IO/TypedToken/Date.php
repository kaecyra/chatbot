<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Date Token
 *
 * @author Francis Caisse <francis.c@vanillaforums.com>
 * @version 1.0
 */
class Date extends AbstractTypedToken {

    /**
     * Validate a parameter as a date
     *
     * Format should be YYYY-mm-dd
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     * @return bool
     * @throws \Exception
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema) {
        $dateExploded = explode("-", $token);
        if (count($dateExploded) !== 3) {
            return false;
        }

        $year = $dateExploded[0];
        $month = $dateExploded[1];
        $day = $dateExploded[2];

        if (!checkdate($month, $day, $year)) {
            $exception = new TokenFormatException("%s is not a valid date.");
            $exception->setToken($token);
            throw $exception;
        }

        return $token;
    }

    /**
     * @inheritDoc
     */
    public function getExample(): string {
        return "YYYY-MM-DD";
    }
}
