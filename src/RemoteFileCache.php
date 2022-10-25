<?php

/**
 * Inane: Cache
 *
 * Inane Cache
 *
 * PHP version 8.1
 *
 * @author Philip Michael Raab<peep@inane.co.za>
 * @package Inane\Cache
 * @category cache
 *
 * @license UNLICENSE
 * @license https://github.com/inanepain/cache/raw/develop/UNLICENSE UNLICENSE
 *
 * @version $Id$
 * $Date$
 */

declare(strict_types=1);

namespace Inane\Cache;

use DateInterval;
use Inane\File\File;
use Inane\Stdlib\Options;
use Psr\SimpleCache\CacheInterface;
use WeakReference;

use function array_filter;
use function array_map;
use function count;
use function md5;
use function time;
use const DIRECTORY_SEPARATOR;
use const false;
use const null;
use const true;

/**
 * Remote File Cache
 *
 * Caches remote files after retrieving them.
 *
 * @package Inane\Cache
 *
 * @version 0.1.0
 */
class RemoteFileCache implements CacheInterface {
    /**
     * Weak Instance Reference
     *
     * @var \WeakReference
     */
    private WeakReference $instance;

    /**
     * Purge when cache size reaches limit
     *
     * If cache size >= $purgeTriggerLimit after added cache content
     *  a purge is triggered.
     *
     * @var int
     */
    private int $maxCacheSize = 4;

    /**
     * Cache Items
     *
     * @var \Inane\Stdlib\Options
     */
    private Options $cacheItems;

    // CONSTRUCTOR
    // =========++

    /**
     * Remote File Cache Constructor
     *
     * @param string $cachePath cache location
     * @param int $defaultTTL duration items may remain in cache
     *
     * @return void
     */
    public function __construct(
        /**
         * Cache Location
         *
         * @var string
         */
        private string $cachePath = 'data/cache',

        /**
         * How long to keep files in cache
         *
         * default: 86400
         * - 1 day
         *
         * @var int seconds
         */
        private int $defaultTTL = 86400,
    ) {
        $this->instance = WeakReference::create($this);
        $this->cacheItems = new Options();
    }

    // PROTECTED
    // =========

    /**
     * Returns the cache
     *
     * @return \Inane\File\File[]
     */
    protected function cache(): array {
        return array_map(fn($f): File => new File($f), glob($this->cachePath . DIRECTORY_SEPARATOR . "*.cache"));
    }

    /**
     * Returns the cache size
     *
     * @return int
     */
    protected function count(): int {
        return count($this->cache());
    }

    /**
     * Purge expired cache items
     */
    protected function purge(): void {
        array_filter($this->cache(), fn($f): bool => (($f->getMTime() + $this->defaultTTL) < time()) ? $f->unlink() : false);
    }

    /**
     * parseKey
     *
     * @param string $url file url
     *
     * @return \Inane\File\File
     */
    protected function getCacheItem(string $url): File {
        if (!$this->cacheItems->has($url)) {
            $uid = md5($url);
            $cacheItem = [
                'file' => new File($this->cachePath . DIRECTORY_SEPARATOR . "{$uid}.cache"),
                'ttl' => $this->defaultTTL,
                'cache' => $this->instance,
            ];
            $this->cacheItems->set($url, new File($this->cachePath . DIRECTORY_SEPARATOR . "{$uid}.cache"));
        }

        return $this->cacheItems->get($url);
    }

    // PUBLIC
    // ======

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
     */
    public function get(string $key, mixed $default = null): mixed {
        $fi = $this->getCacheItem($key);

        if (!$fi->isValid() || ($fi->isValid() && (($fi->getMTime() + $this->defaultTTL) < time()) || $fi->getSize() < 10))
            $this->set($key, file_get_contents($key));

        return $fi->read();
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool {
        $fi = $this->getCacheItem($key);

        if ($fi->write($value)) {
            if ($this->count() >= $this->maxCacheSize) $this->purge();
            return true;
        }

        return false;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is not a legal value.
     */
    public function delete(string $key): bool {
        $fi = $this->getCacheItem($key);

        $this->cacheItems->unset($key);

        if ($fi->isValid()) if ($fi->remove())
            return true;

        return false;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(bool $expiredOnly = false): bool {
        array_filter($this->cache(), fn($f): bool => (($f->getMTime() + $this->defaultTTL) < time()) ? $f->unlink() : false);
        return true;
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
     * @param mixed            $default Default value to return for keys that do not exist.
     *
     * @return iterable<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is neither a legal value, Traversable nor array.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable {
        $cacheItems = [];

        foreach($keys as $u)
            $cacheItems[$u] = $this->get($u);

        return $cacheItems;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is neither a legal value, Traversable nor array.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool {
        foreach($values as $key => $value) $this->set($key, $value);

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is neither a legal value, Traversable nor array.
     */
    public function deleteMultiple(iterable $keys): bool {
        foreach($keys as $u)
            $this->delete($u);

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the $key string is neither a legal value.
     */
    public function has(string $key): bool {
        return $this->getCacheItem($key)->isValid();
    }
}
