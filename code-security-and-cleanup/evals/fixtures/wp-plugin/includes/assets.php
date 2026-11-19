<?php
/**
 * Scripts and styles.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end assets.
 */
function shelfmark_enqueue_frontend_assets() {
	wp_enqueue_style( 'shelfmark-frontend', SHELFMARK_URL . 'assets/css/frontend.css', array(), SHELFMARK_VERSION );
	wp_enqueue_script(
		'shelfmark-summary-toggle',
		SHELFMARK_URL . 'assets/js/summary-toggle.js',
		array(),
		SHELFMARK_VERSION,
		array( 'strategy' => 'defer' )
	);
	wp_enqueue_script_module( 'shelfmark-frontend', SHELFMARK_URL . 'assets/js/frontend.js', array(), SHELFMARK_VERSION );
}

/**
 * Admin assets.
 */
function shelfmark_enqueue_admin_assets() {
	wp_enqueue_script( 'shelfmark-admin', SHELFMARK_URL . 'assets/js/admin.js', array(), SHELFMARK_VERSION, true );
	wp_localize_script(
		'shelfmark-admin',
		'shelfmarkAdmin',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'settingsNonce' => wp_create_nonce( 'shelfmark_settings' ),
			'importNonce'   => wp_create_nonce( 'shelfmark_import' ),
		)
	);
}
