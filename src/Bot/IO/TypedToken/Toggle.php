<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

/**
 * Toggle Token
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @version 1.0
 */
class Toggle extends AbstractTypedToken {

    /**
     * Validating a parameter as a toggle
     *
     * Format should be (enable|disable)
     *
     * @param string $token
     * @param string $prevToken
     * @param array $tokenSchema
     * @return mixed
     * @throws \Exception
     */
    public function validateToken(string $token, ?string $prevToken, array $tokenSchema) {
        if (in_array($token, ['enable', 'activate'])) {
            return "enable";
        }

        if (in_array($token, ['disable', 'deactivate'])) {
            return "disable";
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getExample(): string {
        return "enable|disable";
    }
}
