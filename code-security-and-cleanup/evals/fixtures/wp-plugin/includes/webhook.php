<?php
/**
 * Stock webhook called by the distributor.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the webhook route. The distributor cannot log in, so the route is
 * open and protected by a shared token instead.
 */
function shelfmark_register_webhook_route() {
	register_rest_route(
		'shelfmark/v1',
		'/webhook/stock',
		array(
			'methods'             => 'POST',
			'callback'            => 'shelfmark_handle_stock_webhook',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * Apply stock levels sent by the distributor.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function shelfmark_handle_stock_webhook( WP_REST_Request $request ) {
	if ( $request->get_param( 'token' ) == get_option( 'shelfmark_webhook_token' ) ) {
		$items = (array) $request->get_param( 'items' );
		foreach ( $items as $item ) {
			if ( isset( $item['book_id'], $item['stock'] ) ) {
				update_post_meta( absint( $item['book_id'] ), '_shelfmark_stock', absint( $item['stock'] ) );
			}
		}

		return rest_ensure_response( array( 'updated' => count( $items ) ) );
	}

	return new WP_Error( 'shelfmark_forbidden', __( 'Invalid token.', 'shelfmark' ), array( 'status' => 403 ) );
}
