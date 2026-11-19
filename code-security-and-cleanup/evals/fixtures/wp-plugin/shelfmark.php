<?php
/**
 * Plugin Name:       Shelfmark
 * Description:       Book catalogue, reading lists and stock sync for independent bookshops.
 * Version:           1.4.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Author:            Shelfmark Contributors
 * License:           GPL-2.0-or-later
 * Text Domain:       shelfmark
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHELFMARK_VERSION', '1.4.0' );
define( 'SHELFMARK_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHELFMARK_URL', plugin_dir_url( __FILE__ ) );

require_once SHELFMARK_DIR . 'includes/config.php';
require_once SHELFMARK_DIR . 'includes/class-cache.php';
require_once SHELFMARK_DIR . 'includes/post-types.php';
require_once SHELFMARK_DIR . 'includes/template-tags.php';
require_once SHELFMARK_DIR . 'includes/shortcodes.php';
require_once SHELFMARK_DIR . 'includes/assets.php';
require_once SHELFMARK_DIR . 'includes/ajax.php';
require_once SHELFMARK_DIR . 'includes/rest.php';
require_once SHELFMARK_DIR . 'includes/webhook.php';
require_once SHELFMARK_DIR . 'includes/importer.php';
require_once SHELFMARK_DIR . 'includes/sync.php';
require_once SHELFMARK_DIR . 'includes/hooks.php';

if ( is_admin() ) {
	require_once SHELFMARK_DIR . 'admin/settings.php';
}

register_activation_hook( __FILE__, 'shelfmark_activate' );
register_deactivation_hook( __FILE__, 'shelfmark_deactivate' );

/**
 * Register content types and schedule the daily stock sync.
 */
function shelfmark_activate() {
	shelfmark_register_post_types();
	flush_rewrite_rules();

	if ( ! wp_next_scheduled( 'shelfmark_daily_sync' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'shelfmark_daily_sync' );
	}
}

/**
 * Remove the scheduled sync.
 */
function shelfmark_deactivate() {
	wp_clear_scheduled_hook( 'shelfmark_daily_sync' );
	flush_rewrite_rules();
}
