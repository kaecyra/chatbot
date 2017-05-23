<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

/**
 * Mappable interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
interface MappableInterface {

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
     * Get property value
     *
     * @param string $property
     * @return mixed
     */
    public function getProperty(string $property);

}