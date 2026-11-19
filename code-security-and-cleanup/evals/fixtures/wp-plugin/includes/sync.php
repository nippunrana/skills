<?php
/**
 * Daily stock sync with the distributor.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch stock levels and store them on the books.
 */
function shelfmark_run_sync() {
	$key = shelfmark_distributor_key();
	if ( '' === $key ) {
		return;
	}

	$cache = new \Shelfmark\Cache( 'stock' );
	$stock = $cache->remember(
		array( 'endpoint' => 'stock' ),
		static function () use ( $key ) {
			$response = wp_safe_remote_get(
				'https://api.distributor.example/v2/stock',
				array( 'headers' => array( 'Authorization' => 'Bearer ' . $key ) )
			);
			if ( is_wp_error( $response ) ) {
				return array();
			}
			return (array) json_decode( wp_remote_retrieve_body( $response ), true );
		}
	);

	foreach ( $stock as $book_id => $count ) {
		update_post_meta( absint( $book_id ), '_shelfmark_stock', absint( $count ) );
	}
}

/*
function shelfmark_sync_v1_fetch( $page ) {
	$response = wp_remote_get( 'http://api.distributor.example/v1/stock?page=' . $page );
	if ( is_wp_error( $response ) ) {
		return array();
	}
	return json_decode( wp_remote_retrieve_body( $response ), true );
}
*/
