<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\IO;

use Kaecyra\ChatBot\Client\ClientInterface;

/**
 * ParserResponse
 *
 * This object is returned by a ParserInterface::parse() call, and describes the result of the parsing operation.
 *
 * @package Kaecyra\ChatBot\Bot\IO
 * @author Tim Gunter <tim@vanillaforums.com>
 */
class ParserResponse {

    const STATUS_ERROR = 'ERROR';
    const STATUS_OK = 'OK';
    const STATUS_OK_CONFIRM = 'OK_CONFIRM';
    const STATUS_CONTINUE = 'CONTINUE';
    const STATUS_CANCEL = 'CANCEL';

    /**
     * Override status
     * @var string
     */
    protected $status;

    /**
     * List of generated errors
     * @var array
     */
    protected $errors;

    /**
     * ParserResponse constructor
     *
     */
    public function __construct() {
        $this->clearErrors();
    }

    /**
     * Get parser response status
     *
     * @return mixed
     */
    public function getStatus() {
        return !is_null($this->status) ? $this->status : (count($this->errors) ? self::STATUS_ERROR : self::STATUS_OK);
    }

    /**
     * Set override status
     *
     * @param string $status
     * @return ParserResponse
     */
    public function setStatus(string $status): ParserResponse {
        $this->status = $status;
        return $this;
    }

    /**
     * Add an error to the response
     *
     * @param string $error
     * @param array $arguments
     */
    public function addError(string $error, array $arguments = []) {
        array_push($this->errors, [
            "error" => $error,
            "arguments" => $arguments
        ]);
    }

    /**
     * Prepend an error to the response
     *
     * @param string $error
     * @param array $arguments
     */
    public function prependError(string $error, array $arguments = []) {
        array_unshift($this->errors, [
            "error" => $error,
            "arguments" => $arguments
        ]);
    }

    /**
     * Get list of errors
     *
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Get client-formatted error array
     *
     * @param ClientInterface $client
     * @return array
     */
    public function getFormattedErrors(ClientInterface $client): array {
        $emphasized = [];
        foreach ($this->errors as $error) {
            $args = array_map([$client,'emphasize'], $error['arguments']);
            $emphasized[] = vsprintf($error['error'], $args);
        }
        return $emphasized;
    }

    /**
     * Clear all errors
     *
     * @return ParserResponse
     */
    public function clearErrors(): ParserResponse {
        $this->errors = [];
        return $this;
    }

    /**
     * Add bulk errors to response
     *
     * @param array $errors
     */
    public function addBulkErrors(array $errors) {
        foreach ($errors as $error) {
            if (array_key_exists('error', $error) && array_key_exists('arguments', $error)) {
                array_push($this->errors, $error);
            }
        }
    }

}