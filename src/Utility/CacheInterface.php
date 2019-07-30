<?php

namespace Kaecyra\ChatBot\Utility;

/**
 * Interface CacheInterface
 */
Interface CacheInterface {
    public function fetch(string $cacheKey);
    public function store(string $cacheKey, $value, $ttl = 604800): bool;
    public function invalidate(string $cacheKey): bool;
    public function invalidateAll();
}
