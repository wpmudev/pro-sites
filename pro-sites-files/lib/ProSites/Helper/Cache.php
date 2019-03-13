<?php

/**
 * Object cache related functionality.
 *
 * @since      3.6.1
 * @package    ProSites
 * @subpackage Helper
 */
class ProSites_Helper_Cache {

	/**
	 * Cache key for cache version.
	 *
	 * @var $cache_version_key
	 */
	private static $cache_version_key = 'psts_cache_version';

	/**
	 * Wrapper for wp_cache_set in PS.
	 *
	 * Set cache using this method so that
	 * we can delete them without flushing
	 * the object cache as whole. This cache can be
	 * deleted using normal wp_cache_delete.
	 *
	 * @param int|string $key    The cache key to use for retrieval later.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Enables the same key
	 *                           to be used across groups. Default empty.
	 * @param int        $expire Optional. When to expire the cache contents, in seconds.
	 *                           Default 0 (no expiration).
	 *
	 * @since 1.1.6
	 *
	 * @return bool False on failure, true on success.
	 */
	public static function set_cache( $key, $data, $group = '', $expire = 0 ) {
		// Get the current version.
		$version = wp_cache_get( self::$cache_version_key );

		// In case version is not set, set now.
		if ( empty( $version ) ) {
			// In case version is not set, use default 1.
			$version = 1;

			// Set cache version.
			wp_cache_set( self::$cache_version_key, $version );
		}

		// Add to cache array with version.
		$data = array(
			'data'    => $data,
			'version' => $version,
		);

		// Set to WP cache.
		return wp_cache_set( $key, $data, $group, $expire );
	}

	/**
	 * Wrapper for wp_cache_get function in PS.
	 *
	 * Use this to get the cache values set using set_cache method.
	 *
	 * @param int|string $key     The key under which the cache contents are stored.
	 * @param string     $group   Optional. Where the cache contents are grouped. Default empty.
	 * @param bool       $force   Optional. Whether to force an update of the local cache from the persistent
	 *                            cache. Default false.
	 * @param bool       $found   Optional. Whether the key was found in the cache (passed by reference).
	 *                            Disambiguates a return of false, a storable value. Default null.
	 *
	 * @since 1.1.6
	 *
	 * @return bool|mixed False on failure to retrieve contents or the cache
	 *                      contents on success
	 */
	public static function get_cache( $key, $group = '', $force = false, &$found = null ) {
		// Get the current version.
		$version = wp_cache_get( self::$cache_version_key );

		// Do not continue if version is not set.
		if ( empty( $version ) ) {
			return false;
		}

		// Get the cache value.
		$data = wp_cache_get( $key, $group, $force, $found );

		// Return only data.
		if ( isset( $data['version'] ) && $version === $data['version'] && ! empty( $data['data'] ) ) {
			return $data['data'];
		}

		return false;
	}

	/**
	 * Refresh the whole PS cache.
	 *
	 * We can not delete the cache by group. So use
	 * this method to refresh the cache using version.
	 *
	 * @since 1.1.6
	 *
	 * @return bool
	 */
	public static function refresh_cache() {
		// Increment the version.
		$inc = wp_cache_incr( self::$cache_version_key );

		return $inc ? true : false;
	}
}