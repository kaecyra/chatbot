<?php

/**
 * @license MIT
 * @copyright 2010-2019 Tim Gunter
 */

namespace Kaecyra\ChatBot\Bot\Map;

/**
 * Data access trait
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 */
trait DataAccessTrait {

    /**
     * Get a store variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null) {
        return valr($key, $this->data, $default);
    }

    /**
     * Set a store value
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($key, $value) {
        setvalr($key, $this->data, $value);
        return $value;
    }

    /**
     * Check if key exists
     *
     * @param string $key
     */
    public function has($key): bool {
        $key = trim($key);
        $path = explode('.', $key);
        $pathLength = count($path);
        $target = &$this->data;
        for ($i = 1; $i <= $pathLength; ++$i) {
            $subKey = $path[$i - 1];

            // no such key!
            if (!isset($target[$subKey]) || ($i < $pathLength && !is_array($target[$subKey]))) {
                return false;
            }

            if ($i < $pathLength) {
                $target = &$target[$subKey];
            }
        }
        return true;
    }

    /**
     * Unset a key
     *
     * @param string $key
     * @return boolean unset success or failure
     */
    public function delete($key): bool {
        $key = trim($key);
        $path = explode('.', $key);
        $pathLength = count($path);
        $target = &$this->data;
        for ($i = 1; $i <= $pathLength; ++$i) {
            $subKey = $path[$i - 1];

            // no such key!
            if (!isset($target[$subKey]) || ($i < $pathLength && !is_array($target[$subKey]))) {
                return false;
            }

            if ($i < $pathLength) {
                $target = &$target[$subKey];
            }
        }
        unset($target[$subKey]);
        return true;
    }

    /**
     * Push data onto key
     *
     * @param string $key
     * @param array $data
     */
    public function push($key, $data) {
        if (!array_key_exists($key, $this->data) || !is_array($this->data[$key])) {
            $this->data[$key] = [];
        }
        array_push($this->data[$key], $data);
    }
}