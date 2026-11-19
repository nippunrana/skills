<?php
/**
 * Book list markup for the [shelfmark_books] shortcode.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="shelfmark-books">
	<h2><?php echo esc_html( $heading ); ?></h2>
	<ul>
		<?php foreach ( $books as $book ) : ?>
			<li class="shelfmark-book" data-book-id="<?php echo esc_attr( $book->ID ); ?>">
				<a href="<?php echo esc_url( get_permalink( $book ) ); ?>"><?php echo esc_html( get_the_title( $book ) ); ?></a>
				<?php if ( $show_ratings ) : ?>
					<span class="shelfmark-stars"><?php echo esc_html( shelfmark_rating_stars( $book->ID ) ); ?></span>
				<?php endif; ?>
				<span class="shelfmark-price"><?php echo esc_html( shelfmark_get_price( $book->ID ) ); ?></span>
				<button type="button" class="shelfmark-summary-toggle" aria-expanded="false" onclick="shelfmarkToggleSummary(this)"><?php esc_html_e( 'Summary', 'shelfmark' ); ?></button>
				<div class="shelfmark-summary"><?php echo wp_kses_post( get_the_excerpt( $book ) ); ?></div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
