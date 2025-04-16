<?php

namespace WPMVC\Contracts;

use WPMVC\Config;

/**
 * Cacheable contract.
 * Interface for Cache class.
 *
 * @author Alejandro Mostajo
 * @license MIT
 * @package Amostajo\WPPluginCore
 * @version 3.1.6
 */
interface Cacheable
{
	/**
	 * Default constructor.
	 * @since 1.0.0
	 * @param array $config Config settings.
	 */
	public function __construct( Config $config );

	/**
	 * Static constructor.
	 * @since 1.0.0
	 * @param array $config Config settings.
	 */
	public static function init( Config $config );

	/**
	 * Returns value stored in cache.
	 * @since 1.0.0
	 * 
	 * @param string $key     Cache key name.
	 * @param mixed  $default Defuault return value.
	 * 
	 * @return mixed
	 */
	public static function get( $key, $default = null );

	/**
	 * Adds a value to cache.
	 * @since 1.0.0
	 * @param string $key     Main plugin object as reference.
	 * @param mixed  $value   Value to cache.
	 * @param int  	 $expires Expiration time in minutes.
	 */
	public static function add( $key, $value, $expires );

	/**
	 * Returns flag if a given key has a value in cache or not.
	 * @since 1.0.0
	 * @param string $key Cache key name.
	 * @return bool
	 */
	public static function has( $key );

	/**
	 * Returns the value of a given key.
	 * If it doesn't exist, then the value pass by is returned.
	 * @since 1.0.0
	 * @param string   $key     Main plugin object as reference.
	 * @param int  	   $expires Expiration time in minutes.
	 * @param callable $value   Callable that returns value to cache.
	 * @return mixed
	 */
	public static function remember( $key, $expires, $callable );

	/**
	 * Removes a key / value from cache.
	 * @since 1.0.0
	 * @param string $key Cache key name.
	 */
	public static function forget( $key );

	/**
	 * Flushes all cache keys and values.
	 * @since 1.0.0
	 */
	public static function flush();
}