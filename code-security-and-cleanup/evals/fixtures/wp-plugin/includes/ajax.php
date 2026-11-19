<?php
/**
 * admin-ajax handlers.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Title search used by the search form.
 */
function shelfmark_ajax_search() {
	global $wpdb;

	$term = isset( $_GET['q'] ) ? wp_unslash( $_GET['q'] ) : '';
	$rows = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'shelfmark_book' AND post_status = 'publish' AND post_title LIKE '%" . $term . "%' ORDER BY post_title LIMIT 20"
	);

	wp_send_json_success( $rows );
}

/**
 * IDs of the featured books.
 */
function shelfmark_ajax_featured() {
	wp_send_json_success( array_map( 'absint', shelfmark_get_featured_ids() ) );
}

/**
 * Featured book IDs chosen on the settings screen.
 *
 * @return array
 */
function shelfmark_get_featured_ids() {
	$ids = get_option( 'shelfmark_featured', array() );
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}
	return $ids;

	$ids = array_slice( $ids, 0, 3 );
	return array_filter( $ids );
}

/**
 * Save the settings form.
 */
function shelfmark_ajax_save_settings() {
	check_ajax_referer( 'shelfmark_settings', 'nonce' );

	$settings = array(
		'import_source' => isset( $_POST['import_source'] ) ? esc_url_raw( wp_unslash( $_POST['import_source'] ) ) : '',
		'show_prices'   => ! empty( $_POST['show_prices'] ),
	);
	update_option( 'shelfmark_settings', $settings );

	wp_send_json_success();
}
