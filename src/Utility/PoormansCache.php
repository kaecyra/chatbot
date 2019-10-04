<?php

namespace Kaecyra\ChatBot\Utility;

class PoormansCache implements CacheInterface {
    /**
     * Static var where we cache our poor man's values
     *
     * @var array
     */
    private static $storage = [];

    /**
     * Fetch from cache using cache key
     * Returns true and the value on success
     * Returns null on un-existing cache key
     * Returns false on too old ttl
     *
     * @param string $cacheKey
     * @return mixed
     */
    public function fetch(string $cacheKey) {
        if (!isset(self::$storage[$cacheKey]['value'])) {
            return null;
        } elseif (self::$storage[$cacheKey]['ttl'] > time() ? false : true) {
            unset(self::$storage[$cacheKey]);
            return false;
        }

        return self::$storage[$cacheKey]['value'];
    }

    /**
     * Invalidate a cache key and its value
     *
     * @param string $cacheKey
     * @return bool
     */
    public function invalidate(string $cacheKey): bool {
        if (isset(self::$storage[$cacheKey])) {
            unset(self::$storage[$cacheKey]);
            return true;
        }

        return false;
    }

    /**
     * Store a value in the cache
     *
     * @param string $cacheKey
     * @param $value
     * @param int $ttl 604800 is 1 week in seconds and the default ttl
     * @return bool
     */
    public function store(string $cacheKey, $value, $ttl = 604800): bool {
        self::$storage[$cacheKey]['value'] = $value;
        self::$storage[$cacheKey]['ttl'] = $ttl + time();
        return true;
    }

    /**
     * Clear all cache
     */
    public function invalidateAll() {
        self::$storage = [];
    }
}
