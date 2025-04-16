<?php

use WPMVC\PHPFastCache\phpFastCache;
use \PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
	/**
	 * Cache
	 */
	protected $cache;

	/**
	 * Setup configuration.
	 */
	protected function setUp(): void
	{
		phpFastCache::setup([
			'storage'		=> 'auto',
			'path'			=> __DIR__ . '/../cache/',
			'securityKey'	=> '',
			'fallback'		=> [],
			'htaccess'		=> true,
			'server'		=> [],

		]);

		$this->cache = phpFastCache();
	}

	/**
	 * Test functions existance.
	 */
	public function testPhpFastCacheFunction()
	{
		$this->assertTrue(function_exists('phpFastCache'));
		$this->assertTrue(function_exists('__c'));
	}

	/**
	 * Test class creation.
	 */
	public function testPhpFastCacheClass()
	{
		$this->assertNotNull($this->cache);
	}

	/**
	 * Test get and set functions.
	 */
	public function testGetSet()
	{
		$this->cache->set('testkey', 404, 100);

		$this->assertEquals($this->cache->get('testkey'), 404);
	}

	/**
	 * Test delete function.
	 */
	public function testDeleteExists()
	{
		$this->assertTrue($this->cache->isExisting('testkey'));

		$this->cache->delete('testkey');

		$this->assertNull($this->cache->get('testkey'));
	}

    /**
     * Test delete function.
     */
    public function test__setChmodAuto()
    {
        $chmod = phpFastCache::__setChmodAuto([]);
        $this->assertEquals(511, $chmod);
    }

}
