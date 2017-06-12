<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

use Kaecyra\AppCommon\Log\Tagged\TaggedLogInterface;
use Kaecyra\AppCommon\Log\Tagged\TaggedLogTrait;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use \Exception;

/**
 * Abstract map container
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class Map implements LoggerAwareInterface, TaggedLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use TaggedLogTrait;

    const MAP_KEY = '{type}::{property}({value})';

    /**
     * Map storage
     * @var array
     */
    protected $maps;

    /**
     * Object storage
     * @var string
     */
    protected $objects;

    /**
     * Callback storage
     * @var array
     */
    protected $callbacks;

    /**
     * Prepare map storage
     *
     */
    public function __construct() {
        $this->purge();
    }

    /**
     * Data mapper
     *
     * @param MappableInterface $mappedObject
     */
    public function map(MappableInterface $mappedObject) {
        // Get entity type
        $type = $mappedObject->getMapType();
        $hash = $mappedObject->getMapHash();
        $cleanup = array_key_exists($hash, $this->objects) ? true : false;
        $this->objects[$hash] = [
            'type' => $type,
            'stale' => $mappedObject->getStaleHandling(),
            'expires' => $mappedObject->getExpiry() ? time()+$mappedObject->getExpiry() : 0,
            'object' => $mappedObject
        ];

        // Re-mapping an existing item, clean it up
        if ($cleanup) {
            $indexes = [];
            foreach ($this->maps as $mapEntity => $mapHash) {
                if ($mapHash == $hash) {
                    $indexes[] = $mapEntity;
                }
            }
            foreach ($indexes as $index) {
                unset($this->maps[$index]);
            }
        }

        // Map object with each of its mapped properties
        foreach ($mappedObject->getMappedProperties() as $prop => $a) {
            $propValue = $mappedObject->getProperty($prop);
            $entityMapping = str_replace([
                '{type}',
                '{property}',
                '{value}'
            ],[
                $type,
                $prop,
                $propValue
            ],self::MAP_KEY);
            $this->maps[$entityMapping] = $hash;
        }
    }

    /**
     * Data unmapper
     *
     * @param string $type type of entry. User, Room, etc.
     * @param string $prop property to look up. id, name, etc.
     * @param mixed $value entry key
     * @return MappableInterface
     */
    public function unmap(string $type, string $prop, $value): MappableInterface {
        $entityMapping = str_replace([
            '{type}',
            '{property}',
            '{value}'
        ],[
            $type,
            $prop,
            $value
        ],self::MAP_KEY);

        if (!array_key_exists($entityMapping, $this->maps)) {
            throw new MapNotFoundException("Cannot unmap entity '{$entityMapping}'");
        }

        $hash = $this->maps[$entityMapping];
        if (!array_key_exists($hash, $this->objects)) {
            throw new MapNotFoundException("Hash missing from object store '{$hash}'");
        }

        switch ($this->objects[$hash]['stale']) {

            case MappableInterface::STALE_RETURN_REFRESH_ASYNC:
                $isStale = ($this->objects[$hash]['expiry'] && $this->objects[$hash]['expiry'] < time());
                if ($isStale) {
                    if (!array_key_exists($type, $this->callbacks) || !is_callable($this->callbacks[$type])) {
                        $this->tLog(LogLevel::WARNING, "Refresh callback missing for '{type}'", [
                            'type' => $type
                        ]);
                    } else {
                        $this->tLog(LogLevel::INFO, "Async refreshing mapped object {entity}", [
                            'entity' => $entityMapping
                        ]);
                        $this->callbacks[$type]($this->objects[$hash]['object']);
                    }
                }
                return $this->objects[$hash]['object'];
                break;

            // Objects don't expire
            case MappableInterface::NO_EXPIRE:
                return $this->objects[$hash]['object'];
                break;

            // Stale objects are directly returned (de-facto no expiry)
            case MappableInterface::STALE_RETURN:
            default:
                return $this->objects[$hash]['object'];
                break;
        }
    }

    /**
     * Forget a mapped object
     *
     * @param MappableInterface $mappedObject
     */
    public function forget(MappableInterface $mappedObject) {
        // Get entity type
        $type = $mappedObject->getMapType();
        $hash = $mappedObject->getMapHash();

        // Forget each mapped property
        foreach ($mappedObject->getMappedProperties() as $prop => $a) {
            $propValue = $mappedObject->getProperty($prop);
            $entityMapping = str_replace([
                '{type}',
                '{property}',
                '{value}'
            ],[
                $type,
                $prop,
                $propValue
            ],self::MAP_KEY);
            unset($this->maps[$entityMapping]);
        }

        // Forget hash
        unset($this->objects[$hash]);
    }

    /**
     * Get all objects by type
     *
     * @param string $type
     */
    public function getAll(string $type): array {
        return array_reduce($this->objects, function(array $carry, array $item) use ($type) {
            if ($item['object']->getMapType() != $type) {
                $carry[] = $item['object'];
            }
            return $carry;
        }, []);
    }

    /**
     * Set refresh callback
     *
     * @param string $type
     * @param callable $callback
     */
    public function setTypeRefreshCallback(string $type, callable $callback) {
        $this->callbacks[$type] = $callback;
    }

    /**
     * Purge the mapper
     *
     */
    public function purge() {
        $this->maps = [];
        $this->objects = [];
        $this->callbacks = [];
    }

}