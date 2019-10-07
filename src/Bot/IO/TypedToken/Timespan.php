<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Timespan Token
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 1.0
 */
class Timespan extends AbstractTypedToken {

    /**
     * Validating a parameter as a timespan
     *
     * Format should be {1,3} (day|days|week|weeks|month|months|year|years)
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     * @return mixed
     * @throws \Exception
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema) {
        $match = preg_match('~\b(\d{1,3}\s(?:day|days|month|months|year|years))\b~', "{$prevToken} {$token}", $matches);
        if (!$match) {
            return false;
        }

        return "{$prevToken} {$token}";
    }

    /**
     * @inheritDoc
     */
    public function getExample(): string {
        return "X (days|months|years)";
    }
}
