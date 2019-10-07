<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Search Token
 *
 * Token used to collect unknown data. Will collect anything that isn't specifically excluded
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 1.0
 */
class Search extends AbstractTypedToken {

    /**
     * Validating a parameter as a "search"
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     * @return mixed
     * @throws \Exception
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema) {
        $exclude = $tokenSchema['exclude'] ?? [];
        if (in_array($token, $exclude)) {
            return false;
        }

        return $token;
    }

    /**
     * @inheritDoc
     */
    public function getExample(): string {
        return "anything";
    }
}
