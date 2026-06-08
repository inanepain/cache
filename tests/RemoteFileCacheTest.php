<?php

/**
 * Inane: Cache
 *
 * Some simple caching tools implementing PSR-6 and PSR-16.
 *
 * $Id$
 * $Date$
 *
 * PHP version 8.5
 *
 * @author   Philip Michael Raab<philip@cathedral.co.za>
 * @package  inanepain\cache
 * @category cache
 *
 * @license  UNLICENSE
 * @license  https://unlicense.org/UNLICENSE UNLICENSE
 *
 * _version_ $version
 */

declare(strict_types = 1);

namespace Inane\Cache\Tests;

use Inane\Cache\RemoteFileCache;
use Inane\Stdlib\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

use function array_keys;
use function clearstatcache;
use function glob;
use function is_dir;
use function md5;
use function mkdir;
use function rmdir;
use function str_repeat;
use function unlink;

/**
 * Class RemoteFileCacheTest
 *
 * A test suite for the RemoteFileCache class, testing functionality such as storing, retrieving,
 * verifying, deleting, and clearing cache items, while ensuring proper behavior under various scenarios.
 */
final class RemoteFileCacheTest extends TestCase {
    /**
     * The directory where cache files are stored.
     */
    private string $cacheDir;

    /**
     * The cache instance used for storing and retrieving data.
     */
    private RemoteFileCache $cache;

    /**
     * Sets up the test environment by initializing the remote file cache directory
     * and ensuring it is clean before each test. Instantiates a new RemoteFileCache
     * instance using the cache directory and a specified cache lifetime.
     *
     * @return void
     *
     * @throws \RuntimeException If the cache directory cannot be created or accessed.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->cacheDir = 'data/cache/phpunit-remote-file-cache';

        // Ensure a clean cache directory for each test
        if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0777, true);
        $this->cleanupCacheDir();

        $this->cache = new RemoteFileCache($this->cacheDir, 3600);
    }

    /**
     * Cleans up the test environment by removing the cache directory and its contents.
     * This ensures that no residual data remains after each test execution.
     *
     * @return void
     *
     * @throws \RuntimeException If the cache directory cannot be removed or accessed.
     */
    protected function tearDown(): void {
        $this->cleanupCacheDir();
        @rmdir($this->cacheDir);
        parent::tearDown();
    }

    /**
     * Cleans up the cache directory by removing all files with a .cache extension and clearing the file status cache.
     *
     * This method scans the specified cache directory for files with a .cache extension
     * and deletes them. It also clears the PHP file status cache to ensure no stale file information is retained.
     *
     * @return void
     *
     * @throws \RuntimeException If an error occurs while attempting to delete a file in the cache directory.
     */
    private function cleanupCacheDir(): void {
        clearstatcache();
        foreach(glob($this->cacheDir . '/*.cache') ?: [] as $f) @unlink($f);
    }

    /**
     * Tests the set, get, has, and delete operations of the cache system to ensure proper functionality.
     *
     * This method performs the following steps:
     * - Stores a key-value pair in the cache and verifies that the operation succeeds.
     * - Checks that the `has()` method correctly identifies the presence of the cache entry.
     * - Retrieves the stored value using the `get()` method and confirms that the value matches the original input.
     * - Verifies that a cache file is created on disk with an appropriate naming convention.
     * - Deletes the cache entry and ensures it is no longer present using both the `delete()` and `has()` methods.
     *
     * @return void
     *
     * @throws \RuntimeException If the cache directory or file operations fail during the test.
     */
    public function testSetGetAndHasAndDelete(): void {
        $key = 'unit-test://remote-file-cache/a';
        $value = str_repeat('content-', 2); // ensure > 10 bytes to avoid auto-refetch

        $this->assertTrue($this->cache->set($key, $value));

        // has() should be true after set
        $this->assertTrue($this->cache->has($key));

        // get() should read back what we stored
        $this->assertSame($value, $this->cache->get($key));

        // file should exist on disk (md5-based name)
        $expectedPrefix = $this->cacheDir . '/' . md5($key) . '-';
        $files = glob($expectedPrefix . '*.cache') ?: [];
        $this->assertNotEmpty($files, 'Cache file was not created');

        // delete removes cache
        $this->assertTrue($this->cache->delete($key));
        $this->assertFalse($this->cache->has($key));
    }

    /**
     * Tests multiple cache operations including setting, retrieving, and deleting multiple cache entries.
     *
     * This method performs the following operations:
     * - Sets multiple key-value pairs in the cache.
     * - Retrieves the previously set key-value pairs using `getMultiple` and verifies their correctness.
     * - Deletes the key-value pairs using `deleteMultiple` and verifies their deletion.
     *
     * @return void
     *
     * @throws \RuntimeException If setting multiple cache entries fails.
     * @throws \RuntimeException If retrieving multiple cache entries fails.
     * @throws \RuntimeException If deleting multiple cache entries fails.
     * @throws \RuntimeException|InvalidArgumentException If cache entries are not properly cleared after deletion.
     */
    public function testMultipleOperations(): void {
        $pairs = [
            'unit-test://remote-file-cache/one'   => str_repeat('alpha-', 3),
            'unit-test://remote-file-cache/two'   => str_repeat('beta-', 3),
            'unit-test://remote-file-cache/three' => str_repeat('gamma-', 3),
        ];

        $this->assertTrue($this->cache->setMultiple($pairs));

        // Read back using getMultiple
        $values = $this->cache->getMultiple(array_keys($pairs));
        $this->assertSame($pairs, $values);

        // Delete multiple
        $this->assertTrue($this->cache->deleteMultiple(array_keys($pairs)));

        foreach(array_keys($pairs) as $k) {
            $this->assertFalse($this->cache->has($k));
        }
    }

    /**
     * Tests that invoking the `clear` method removes all items from the cache.
     *
     * This method verifies that items added to the cache are successfully removed
     * after calling the `clear` method. It first ensures that the cache is populated
     * with test data, then invokes the `clear` method and checks that the cache
     * directory no longer contains any cached files.
     *
     * @return void
     *
     * @throws \RuntimeException If an error occurs while interacting with the cache system.
     */
    public function testClearRemovesAllItems(): void {
        $keys = [
            'unit-test://remote-file-cache/x' => str_repeat('xxx-', 3),
            'unit-test://remote-file-cache/y' => str_repeat('yyy-', 3),
        ];

        foreach($keys as $k => $v) try {
            $this->cache->set($k, $v);
        } catch (RuntimeException|InvalidArgumentException $e) {

        }

        // Sanity: files created
        $this->assertNotEmpty(glob($this->cacheDir . '/*.cache') ?: []);

        $this->assertTrue($this->cache->clear());
        $this->assertEmpty(glob($this->cacheDir . '/*.cache') ?: []);
    }
}
