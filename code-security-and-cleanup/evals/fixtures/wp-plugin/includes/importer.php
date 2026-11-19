<?php
/**
 * Cover image importer.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Look up a cover image from a metadata URL entered by an editor.
 */
function shelfmark_ajax_import_cover() {
	check_ajax_referer( 'shelfmark_import', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}

	$url      = isset( $_POST['cover_url'] ) ? esc_url_raw( wp_unslash( $_POST['cover_url'] ) ) : '';
	$response = wp_remote_get(
		$url,
		array(
			'timeout'   => 15,
			'sslverify' => false,
		)
	);
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ), 502 );
	}

	$meta = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $meta ) || empty( $meta['image'] ) ) {
		wp_send_json_error( array( 'message' => 'no image in response' ), 422 );
	}

	wp_send_json_success( array( 'image' => esc_url_raw( $meta['image'] ) ) );
}
