<?php
/**
 * Content types, their meta boxes, templates and patterns.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the book, author and reading-list post types.
 */
function shelfmark_register_post_types() {
	register_post_type(
		'shelfmark_book',
		array(
			'label'        => __( 'Books', 'shelfmark' ),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'books' ),
		)
	);

	register_post_type(
		'shelfmark_author',
		array(
			'label'        => __( 'Authors', 'shelfmark' ),
			'public'       => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'      => array( 'slug' => 'authors' ),
		)
	);

	register_post_type(
		'shelfmark_list',
		array(
			'label'    => __( 'Reading lists', 'shelfmark' ),
			'public'   => false,
			'supports' => array( 'title', 'author' ),
		)
	);
}

/**
 * Save the price, rating and stock fields of a book.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function shelfmark_save_book_meta( $post_id, $post ) {
	if ( ! isset( $_POST['shelfmark_book_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['shelfmark_book_nonce'] ), 'shelfmark_save_book' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['shelfmark_price'] ) ) {
		update_post_meta( $post_id, '_shelfmark_price', (float) wp_unslash( $_POST['shelfmark_price'] ) );
	}
	if ( isset( $_POST['shelfmark_rating'] ) ) {
		update_post_meta( $post_id, '_shelfmark_rating', min( 5, max( 0, (float) wp_unslash( $_POST['shelfmark_rating'] ) ) ) );
	}
}

/**
 * Save the birth year of an author.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function shelfmark_save_author_meta( $post_id, $post ) {
	if ( ! isset( $_POST['shelfmark_author_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['shelfmark_author_nonce'] ), 'shelfmark_save_author' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['shelfmark_born'] ) ) {
		update_post_meta( $post_id, '_shelfmark_born', absint( $_POST['shelfmark_born'] ) );
	}
}

/**
 * Use the plugin's own template for single books and authors when it has one.
 *
 * @param string $template Template chosen by WordPress.
 * @return string
 */
function shelfmark_template_include( $template ) {
	if ( ! is_singular( array( 'shelfmark_book', 'shelfmark_author' ) ) ) {
		return $template;
	}

	$candidate = SHELFMARK_DIR . 'templates/single-' . str_replace( 'shelfmark_', '', get_post_type() ) . '.php';
	return file_exists( $candidate ) ? $candidate : $template;
}

/**
 * Register the block patterns that ship with the plugin.
 */
function shelfmark_register_patterns() {
	$patterns = array(
		'book-grid' => __( 'Staff picks', 'shelfmark' ),
	);

	foreach ( $patterns as $name => $title ) {
		ob_start();
		include SHELFMARK_DIR . 'patterns/' . $name . '.php';
		register_block_pattern(
			'shelfmark/' . $name,
			array(
				'title'   => $title,
				'content' => ob_get_clean(),
			)
		);
	}
}
