<?php
/**
 * Hook registrations.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'shelfmark_register_post_types' );
add_action( 'init', 'shelfmark_register_shortcodes' );
add_action( 'init', 'shelfmark_register_patterns' );
add_action( 'rest_api_init', 'shelfmark_register_rest_routes' );
add_action( 'rest_api_init', 'shelfmark_register_webhook_route' );
add_action( 'wp_enqueue_scripts', 'shelfmark_enqueue_frontend_assets' );
add_action( 'admin_enqueue_scripts', 'shelfmark_enqueue_admin_assets' );
add_action( 'shelfmark_daily_sync', 'shelfmark_run_sync' );
add_filter( 'template_include', 'shelfmark_template_include' );

foreach ( array( 'book', 'author' ) as $shelfmark_type ) {
	add_action( "save_post_shelfmark_{$shelfmark_type}", "shelfmark_save_{$shelfmark_type}_meta", 10, 2 );
}
unset( $shelfmark_type );

add_action( 'wp_ajax_shelfmark_search', 'shelfmark_ajax_search' );
add_action( 'wp_ajax_nopriv_shelfmark_search', 'shelfmark_ajax_search' );
add_action( 'wp_ajax_shelfmark_featured', 'shelfmark_ajax_featured' );
add_action( 'wp_ajax_nopriv_shelfmark_featured', 'shelfmark_ajax_featured' );
add_action( 'wp_ajax_shelfmark_save_settings', 'shelfmark_ajax_save_settings' );
add_action( 'wp_ajax_shelfmark_import_cover', 'shelfmark_ajax_import_cover' );
