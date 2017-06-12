<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\ChatBot\Utility;

use \Memcached;

/**
 * Cache abstraction
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package chatbot
 * @since 1.0
 */
class Cache {

    const REVISION_KEY = 'cache.state.revision';
    const REVISION_TAG = 'rev-%d';

    /**
     * Memcached pool
     * @var \Memcached
     */
    protected $pool;

    /**
     * Current revision
     * @var int
     */
    protected $revision;

    /**
     * Revision key
     * @var string
     */
    protected $revisionKey = 'cache.state.revision';

    /**
     * Revision tag string format
     * @var string
     */
    protected $tagFormat = 'rev-%d';

    /**
     * Final key format
     * @var string
     */
    protected $keyFormat = '';

    /**
     * Prepare cache
     *
     * @param Memcached $cache
     */
    public function __construct(
        Memcached $cache
    ) {
        $this->pool = $cache;
    }

    /**
     * Set the revision number cache key
     *
     * @param string $key
     * @return Cache
     */
    public function setRevisionKey(string $key): Cache {
        $this->revisionKey = $key;
        return $this;
    }

    /**
     * Set the revision tag format
     *
     * sprintf-compatible, should have a single %d for the revision tag number
     *
     * @param string $format
     * @return Cache
     */
    public function setTagFormat(string $format): Cache {
        $this->tagFormat = $format;
        return $this;
    }

    /**
     * Set the final key format
     *
     * sprintf-compatible, should have two %s for revision tag and original key respectively
     *
     * @param string $format
     * @return Cache
     */
    public function setKeyFormat(string $format): Cache {
        $this->keyFormat = $format;
        return $this;
    }

    /**
     * Get revision number
     *
     * @return int
     */
    public function getRevision(): int {
        if (is_null($this->revision)) {
            $this->revision = $this->pool->get($this->revisionKey);
        }
        return $this->revision;
    }

    /**
     * Set revision number
     *
     * @param int $revision
     * @return Cache
     */
    public function setRevision(int $revision): Cache {
        $this->pool->set($this->revisionKey, $revision);
        $this->revision = $revision;
        return $this;
    }

    /**
     * Increment revision number
     *
     * @return int
     */
    public function incrementRevision(): int {
        $this->revision = $this->pool->increment($this->revisionKey, 1);
        return $this;
    }

    /**
     * Get revision tag
     *
     * @return string
     */
    public function getRevisionTag(): string {
        $rev = $this->getRevision();
        return sprintf($this->tagFormat, $rev);
    }

    /**
     * Revision-tag a key
     *
     * @param string $key
     * @return string
     */
    public function getTaggedKey(string $key): string {
        return sprintf($this->keyFormat, $this->getRevisionTag(), $key);
    }

    /**
     * Pass unmatched calls on to cache pool
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        if (method_exists($this->pool, $name)) {
            return call_user_func_array([$this->pool, $name], $arguments);
        }
        throw new \BadMethodCallException($name);
    }

    /**
     * Get a get from the cache
     *
     * @see Memcached::get()
     * @param string $key
     * @param Callable $callback
     * @param float $token
     * @return mixed
     */
    public function get(string $key, $callback, $token) {
        return $this->pool->get($this->getTaggedKey($key), $callback, $token);
    }

    /**
     * Get multiple keys from the cache
     *
     * @see Memcached::getMulti()
     * @param array $items
     * @param array $tokens
     * @param int $flags
     * @return array
     */
    public function getMulti(array $items, $tokens = null, int $flags = 0): array {
        $final = [];
        foreach ($items as $k) {
            $final[] = $this->getTaggedKey($k);
        }
        unset($items);

        $result = $this->pool->getMulti($final, $tokens, $flags);
        unset($final);

        $final = [];
        $strip = $this->getTaggedKey('');
        foreach ($result as $k => $v) {
            $final[str_replace($strip, '', $k)] = $v;
        }
        return $final;
    }

    /**
     * Add a key to the cache
     *
     * @see Memcached::add()
     * @param string $key
     * @param type $value
     * @param int $expiration
     * @return bool
     */
    public function add(string $key, $value, int $expiration = 0): bool {
        return $this->pool->add($this->getTaggedKey($key), $value, $expiration);
    }

    /**
     * Set a key in the cache
     *
     * @see Memcached::set()
     * @param string $key
     * @param type $value
     * @param int $expiration
     * @return bool
     */
    public function set(string $key, $value, int $expiration = 0): bool {
        return $this->pool->set($this->getTaggedKey($key), $value, $expiration);
    }

    /**
     * Set multiple keys in the cache
     *
     * @see Memcached::setMulti()
     * @param array $items
     * @param int $expiration
     * @return bool
     */
    public function setMulti(array $items, int $expiration = 0): bool {
        $final = [];
        foreach ($items as $k => $v) {
            $final[$this->getTaggedKey($k)] = $v;
        }
        return $this->pool->setMulti($items, $expiration);
    }

    /**
     * Replace a key in the cache
     *
     * @see Memcached::replace()
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function replace(string $key, $value, int $expiration = 0): bool {
        return $this->pool->replace($this->getTaggedKey($key), $value, $expiration);
    }

    /**
     * Increment a key in the cache
     *
     * @see Memcached::increment()
     * @param string $key
     * @param int $offset
     * @param int $initial
     * @param int $expiry
     * @return int
     */
    public function increment(string $key, int $offset = 1, int $initial = 0, int $expiry = 0): int {
        return $this->pool->increment($this->getTaggedKey($key), $offset, $initial, $expiry);
    }

    /**
     * Decrement a key in the cache
     *
     * @see Memcached::decrement()
     * @param string $key
     * @param int $offset
     * @param int $initial
     * @param int $expiry
     * @return bool
     */
    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expiry = 0): bool {
        return $this->pool->decrement($this->getTaggedKey($key), $offset, $initial, $expiry);
    }

    /**
     * Delete a key from the cache
     *
     * @see Memcached::delete()
     * @param string $key
     * @param int $time
     * @return bool
     */
    public function delete(string $key, int $time = 0): bool {
        return $this->pool->delete($this->getTaggedKey($key), $time);
    }

    /**
     * Delete multiple keys from the cache
     *
     * @see Memcached::deleteMulti()
     * @param array $items
     * @param int $time
     * @return array
     */
    public function deleteMulti(array $items, int $time = 0): array {
        $final = [];
        foreach ($items as $k) {
            $final[] = $this->getTaggedKey($k);
        }
        unset($items);

        $result = $this->pool->deleteMulti($final, $time);
        unset($final);

        $final = [];
        $strip = $this->getTaggedKey('');
        foreach ($result as $k => $v) {
            $final[str_replace($strip, '', $k)] = $v;
        }
        return $final;
    }

    /**
     * Soft-flush cache by incrementing revision
     *
     * @return type
     */
    public function revisionFlush() {
        return $this->incrementRevision();
    }

    /**
     * Hard-flush cache
     *
     * @param int $delay
     */
    public function flush(int $delay = 0) {
        return $this->pool->flush($delay);
    }

}