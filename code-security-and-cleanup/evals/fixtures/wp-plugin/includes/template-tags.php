<?php
/**
 * Template tags.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'shelfmark_the_availability' ) ) {
	/**
	 * Print whether a book is in stock.
	 *
	 * @param int $post_id Book ID. Defaults to the current post.
	 */
	function shelfmark_the_availability( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : get_the_ID();
		$stock   = (int) get_post_meta( $post_id, '_shelfmark_stock', true );

		if ( $stock > 0 ) {
			/* translators: %d: number of copies in stock. */
			$label = sprintf( _n( '%d copy in stock', '%d copies in stock', $stock, 'shelfmark' ), $stock );
		} else {
			$label = __( 'Out of stock', 'shelfmark' );
		}

		printf( '<span class="shelfmark-availability">%s</span>', esc_html( $label ) );
	}
}

/**
 * Formatted price of a book.
 *
 * @param int $post_id Book ID.
 * @return string Empty when the book has no price.
 */
function shelfmark_get_price( $post_id ) {
	$price = get_post_meta( $post_id, '_shelfmark_price', true );
	return '' === $price ? '' : number_format_i18n( (float) $price, 2 );
}

/**
 * Star string for a book's rating.
 *
 * @param int $post_id Book ID.
 * @return string
 */
function shelfmark_rating_stars( $post_id ) {
	$rating = (float) get_post_meta( $post_id, '_shelfmark_rating', true );
	return str_repeat( '★', (int) round( $rating ) );
}
