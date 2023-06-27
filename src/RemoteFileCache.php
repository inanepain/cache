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
use Inane\Stdlib\Options;
use Psr\SimpleCache\CacheInterface;
use WeakReference;

use function array_filter;
use function count;
use function explode;
use function is_null;
use function md5;
use function preg_match;
use function str_ends_with;
use function substr;
use function time;
use const false;
use const null;
use const true;

use Inane\File\{
    File,
    Path
};

/**
 * Remote File Cache
 *
 * Caches remote files after retrieving them.
 *
 * @package Inane\Cache
 *
 * @version 0.3.1
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
     * If cache size >= $purgeTriggerLimit if after adding a file to cache, its size is equal to or grater than this limit a purge is triggered automatically.
     *
     * @var int
     */
    private int $maxCacheSize = 10;

    /**
     * Cache Items
     *
     * @var \Inane\Stdlib\Options
     */
    private Options $cacheItems;

    /**
     * Cache Path
     *
     * @var \Inane\File\Path
     */
    private Path $path;

    // CONSTRUCTOR
    // =========++

    /**
     * Remote File Cache Constructor
     *
     * @param string $cachePath cache location (data/cache)
     * @param int $defaultTTL duration items may remain in cache (86400, 1 day)
     *
     * @return void
     */
    public function __construct(
        /**
         * Cache Location (data/cache)
         *
         * @var string cache location
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
        $this->path = new Path($cachePath);

        if (str_ends_with($cachePath, '/') || str_ends_with($cachePath, '\\'))
            $cachePath = substr($cachePath, 0, -1);

        // $cp = explode('\\', $cachePath);
        // $this->cachePath = implode(DIRECTORY_SEPARATOR, $cp);
    }

	/**
	 * Get cache key for supplied url
	 * 
	 * @since 0.3.0
	 * 
	 * @param string $url source file
	 * 
	 * @return string cache key
	 */
	private static function parseId(string $url): string {
		if(preg_match('/[0-9a-f]{32}/i', $url)) return $url;

	    return md5($url);
	}

    // PROTECTED
    // =========

    /**
     * Read filesystem cache files into class cache container
	 * 
	 * @since 0.3.0
     *
     * @return void
     */
    protected function loadCache(): void {
		foreach($this->cache() as $file) {
			$meta = explode('-', $file->getBasename('.cache'));
			if (count($meta) == 1) $meta[] = $this->defaultTTL;
			[$uid, $ttl] = $meta;
			$this->cacheItems->set($uid, [
				'file' => $file,
                'ttl' => $ttl,
                'cache' => $this->instance,
			]);
		}
    }

    /**
     * Returns the cache
     *
     * @return \Inane\File\File[]
     */
    protected function cache(): array {
        return $this->path->getFiles('*.cache');
        // return array_map(fn($f): File => new File($f), glob($this->cachePath . DIRECTORY_SEPARATOR . "*.cache"));
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
	 * Get or Add item to or from cache
	 * 
	 * @param string $url	cache key
	 * @param null|int $ttl	time to live for cache item if not default
	 * 
	 * @return array|\Inane\Stdlib\Options cache item
	 * 
	 * @throws \Inane\Stdlib\Exception\RuntimeException 
	 */
    protected function getCacheItem(string $url, ?int $ttl = null): array|Options {
        $uid = static::parseId($url);
        if (!$this->cacheItems->has($uid)) {
			if (is_null($ttl)) $ttl = $this->defaultTTL;
            $this->cacheItems->set($uid, [
                'file' => $this->path->getFile("$uid-$ttl.cache"),
                'ttl' => $ttl,
                'cache' => $this->instance,
            ]);
        }

        return $this->cacheItems->get($uid);
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
        $ci = $this->getCacheItem($key);

        if (!$ci->file->isValid() || ($ci->file->isValid() && (($ci->file->getMTime() + $ci->ttl) < time()) || $ci->file->getSize() < 10))
            $this->set($key, file_get_contents($key));

        return $ci->file->read(true);
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
        $ci = $this->getCacheItem($key);
		
        if ($ci->file->write($value)) {
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
        $ci = $this->getCacheItem($key);

        $this->cacheItems->unset(static::parseId($key));

        if ($ci->file->isValid()) if ($ci->file->remove())
            return true;

        return false;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(bool $expiredOnly = false): bool {
        foreach($this->cacheItems as $uid => $ci) $this->delete($uid);
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
        return $this->getCacheItem($key)->file->isValid();
    }
}
