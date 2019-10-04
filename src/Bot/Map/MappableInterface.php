<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

/**
 * Mappable interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface MappableInterface {

    const NO_EXPIRE = 'no_expire';
    const STALE_RETURN = 'stale_return';
    const STALE_RETURN_REFRESH_ASYNC = 'stale_return_refresh_async';

    /**
     * Get mapped properties
     *
     * @retrun array
     */
    public function getMappedProperties(): array;

    /**
     * Get map type
     *
     * Get the key for the mapping array.
     *
     * @return string
     */
    public static function getMapType(): string;

    /**
     * Get map hash
     *
     * Each object should be able to return a hash representing itself uniquely.
     *
     * @return string
     */
    public function getMapHash(): string;

    /**
     * Get property value
     *
     * @param string $property
     * @return mixed
     */
    public function getProperty(string $property);

    /**
     * Get stale handling technique
     *
     * @return string
     */
    public function getStaleHandling(): string;

    /**
     * Get expiry length
     *
     * 0 means doesn't expire
     *
     * @return int
     */
    public function getExpiry(): int;

}