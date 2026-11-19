<?php
/**
 * Single book.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'shelfmark-single-book' ); ?>>
		<h1><?php the_title(); ?></h1>
		<p class="shelfmark-price"><?php echo esc_html( shelfmark_get_price( get_the_ID() ) ); ?></p>
		<p class="shelfmark-stars"><?php echo esc_html( shelfmark_rating_stars( get_the_ID() ) ); ?></p>
		<div class="shelfmark-description"><?php the_content(); ?></div>
	</article>
	<?php
endwhile;

get_footer();
