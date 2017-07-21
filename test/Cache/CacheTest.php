<?php
/**
 * Created by PhpStorm.
 * User: michelecapicchioni
 * Date: 11/07/17
 * Time: 17:56
 */

namespace Cache\Test;


use Cache\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{

    const ROOT_DIRECTORY = __DIR__ . '/../../';

    /**
     * Test if can create the cache
     */
    public function testCanCreateCache()
    {
        $rootDirectory = realpath(CacheTest::ROOT_DIRECTORY);
        $cacheDirectory = $rootDirectory . "/var/cache/";
        $cacheName = 'testCanCreateCache';
        $cache = new Cache($cacheName, $cacheDirectory);
        $cache->store('test', 'test');
        $this->assertFileExists($cacheDirectory . "{$cacheName}.cache");
    }

    /**
     * Test if it saving data
     */
    public function testCanCacheDataRight()
    {
        $rootDirectory = realpath(CacheTest::ROOT_DIRECTORY);
        $cacheDirectory = $rootDirectory . "/var/cache/";
        $cacheName = 'testCanCacheDataRight';
        $cache = new Cache($cacheName, $cacheDirectory);

        $cache->store('string', 'test');
        $cache->store('integer', 10);
        $cache->store('float', 10.5);
        $cache->store('array', [
            'test1' => 'test1',
            'test2' => 'test2',
        ]);

        $this->assertEquals('test', $cache->retrieve('string'));
        $this->assertEquals(10, $cache->retrieve('integer'));
        $this->assertEquals(10.5, $cache->retrieve('float'));
        $array = $cache->retrieve('array');
        $this->assertInternalType('array', $array);
        $this->assertEquals(2, count($array));
        $this->assertEquals('test1', $array['test1']);
    }

}