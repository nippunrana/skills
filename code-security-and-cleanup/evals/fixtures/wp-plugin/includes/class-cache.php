<?php
/**
 * Transient cache wrapper.
 *
 * @package Shelfmark
 */

namespace Shelfmark;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remembers the result of a slow call for a while.
 */
class Cache {

	/**
	 * Cache group, used as a key prefix.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * @param string $group Cache group.
	 */
	public function __construct( string $group ) {
		$this->group = $group;
	}

	/**
	 * Return the cached value for these arguments, producing it first if needed.
	 *
	 * @param array    $args     Arguments that identify the value.
	 * @param callable $producer Called when nothing is cached.
	 * @param int      $ttl      Lifetime in seconds.
	 * @return mixed
	 */
	public function remember( array $args, callable $producer, int $ttl = HOUR_IN_SECONDS ) {
		$key   = $this->key( $args );
		$value = get_transient( $key );

		if ( false === $value ) {
			$value = $producer();
			set_transient( $key, $value, $ttl );
		}

		return $value;
	}

	private function key( array $args ): string {
		return 'shelfmark_' . $this->group . '_' . md5( wp_json_encode( $args ) );
	}

	private function normalize_key( string $raw ): string {
		return substr( preg_replace( '/[^a-z0-9_]/', '_', strtolower( $raw ) ), 0, 40 );
	}
}
