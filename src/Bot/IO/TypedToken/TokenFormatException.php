<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO\TypedToken;

use Exception;

/**
 * Class TokenFormatException
 * @package Kaecyra\ChatBot\Vanilla\TypedToken
 */
class TokenFormatException extends Exception {
    protected $token;

    /**
     * @param $token
     */
    public function setToken($token) {
        $this->token = $token;
    }

    /**
     * @return string
     */
    public function getToken() {
        return $this->token;
    }

}
