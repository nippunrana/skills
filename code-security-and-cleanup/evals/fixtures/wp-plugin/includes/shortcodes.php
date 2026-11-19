<?php
/**
 * Shortcodes.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the plugin's shortcodes.
 */
function shelfmark_register_shortcodes() {
	add_shortcode( 'shelfmark_books', 'shelfmark_books_shortcode' );
	add_shortcode( 'shelfmark_search', 'shelfmark_search_shortcode' );
}

/**
 * [shelfmark_books heading="" count="6" ratings="yes"]
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function shelfmark_books_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'heading' => __( 'Our books', 'shelfmark' ),
			'count'   => 6,
			'ratings' => 'yes',
		),
		$atts,
		'shelfmark_books'
	);

	$heading      = sanitize_text_field( $atts['heading'] );
	$show_ratings = 'yes' === $atts['ratings'];
	$books        = get_posts(
		array(
			'post_type'   => 'shelfmark_book',
			'numberposts' => min( 24, absint( $atts['count'] ) ),
		)
	);

	ob_start();
	include SHELFMARK_DIR . 'views/book-list.php';
	return ob_get_clean();
}

/**
 * [shelfmark_search]
 *
 * @return string
 */
function shelfmark_search_shortcode() {
	ob_start();
	include SHELFMARK_DIR . 'views/search-results.php';
	return ob_get_clean();
}
