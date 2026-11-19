<?php
/**
 * Runtime configuration.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Key for the distributor stock API.
 *
 * Set SHELFMARK_DISTRIBUTOR_KEY in wp-config.php or in the environment.
 *
 * @return string Empty when no key is configured.
 */
function shelfmark_distributor_key() {
	if ( defined( 'SHELFMARK_DISTRIBUTOR_KEY' ) ) {
		return (string) SHELFMARK_DISTRIBUTOR_KEY;
	}

	$key = getenv( 'SHELFMARK_DISTRIBUTOR_KEY' );
	return false === $key ? '' : $key;
}
