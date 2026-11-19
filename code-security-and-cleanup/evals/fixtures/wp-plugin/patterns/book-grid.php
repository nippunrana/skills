<?php
/**
 * Staff picks pattern.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!-- wp:group {"className":"shelfmark-picks","layout":{"type":"constrained"}} -->
<div class="wp-block-group shelfmark-picks">
	<!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading"><?php
		/* translators: Heading above the three books chosen by shop staff. */
		echo esc_html__( 'Staff picks', 'shelfmark' );
	?></h3>
	<!-- /wp:heading -->

	<!-- wp:shortcode -->
	[shelfmark_books count="3" ratings="yes"]
	<!-- /wp:shortcode -->
</div>
<!-- /wp:group -->
