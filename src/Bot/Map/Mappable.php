<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

/**
 * Abstract mappable base
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class Mappable implements MappableInterface {

    /**
     * Mappable properties
     * @var array
     */
    protected $mappableProperties;

    /**
     * Property key for hash generation
     * @var string
     */
    protected $mapHashKey;

    /**
     * Set list of mapped properties
     *
     * @param array $properties
     */
    public function setMappedProperties(string $hashKey, array $properties) {
        if (!array_key_exists($hashKey, $properties)) {
            throw new \Exception("Hash key must be one of the mapped properties.");
        }
        $this->mapHashKey = $hashKey;
        $this->mappableProperties = $properties;
    }

    /**
     * Get list of mapped properties
     *
     * @return array
     */
    public function getMappedProperties(): array {
        return $this->mappableProperties ?? [];
    }

    /**
     * Get mapping name
     *
     * @return string
     */
    public static function getMapType(): string {
        return (new \ReflectionClass(get_called_class()))->getShortName();
    }

    /**
     * Get map hash
     *
     * @return string
     */
    public function getMapHash(): string {
        return sha1($this->getMapType()."-".$this->getProperty($this->mapHashKey));
    }

    /**
     * Get property value
     *
     * @param string $property
     * @throws \Exception
     */
    public function getProperty(string $property) {
        if (!array_key_exists($property, $this->mappableProperties)) {
            throw new \Exception("Tried to get undefined mapped property '{$property}'");
        }

        if (is_callable($this->mappableProperties[$property])) {
            return call_user_func($this->mappableProperties[$property]);
        }
    }

    /**
     * Default stale handling
     *
     * @return string
     */
    public function getStaleHandling(): string {
        return MappableInterface::NO_EXPIRE;
    }

    /**
     * Default expiry
     *
     * @return int
     */
    public function getExpiry(): int {
        return 0;
    }

}