<?php
/**
 * Remove the plugin's options and book meta.
 *
 * @package Shelfmark
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'shelfmark_settings' );
delete_option( 'shelfmark_featured' );
delete_option( 'shelfmark_webhook_token' );

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_shelfmark_' ) . '%'
	)
);
