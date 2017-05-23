<?php

/**
 * @license MIT
 * @copyright 2014-2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

/**
 * Abstract map container
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
abstract class Map {

    /**
     * Map storage
     * @var array
     */
    protected $maps;

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
        // $type, $key, $data

        // Get entity type
        $type = $mappedObject->getMapType();

        // Map object with each of its mapped properties
        foreach ($mappedObject->getMappedProperties() as $prop => $a) {
            $propValue = sha1($mappedObject->getProperty($prop));
            $propValueHash = sha1($propValue);
            $entityMapping = "{$type}::{$prop}({$propValueHash})";
            $this->maps[$entityMapping] = $mappedObject;
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
        $valueHash = sha1($value);
        $entityMapping = "{$type}::{$prop}({$valueHash})";

        if (!array_key_exists($entityMapping, $this->maps)) {
            throw new Exception("Cannot unmap entity '{$entityMapping}'");
        }

        return $this->maps[$entityMapping];
    }

    /**
     * Empty mapper
     *
     */
    public function purge() {
        $this->maps = [];
    }

}