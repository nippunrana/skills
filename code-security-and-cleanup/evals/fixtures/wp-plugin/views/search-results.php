<?php
/**
 * Search form and results for the [shelfmark_search] shortcode.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shelfmark_paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
?>
<div class="shelfmark-search">
	<form method="get">
		<label for="shelfmark-q" class="screen-reader-text"><?php esc_html_e( 'Search books', 'shelfmark' ); ?></label>
		<input type="search" id="shelfmark-q" name="q" value="<?php echo isset( $_GET['q'] ) ? esc_attr( wp_unslash( $_GET['q'] ) ) : ''; ?>">
		<button type="submit"><?php esc_html_e( 'Search', 'shelfmark' ); ?></button>
	</form>
	<?php if ( isset( $_GET['q'] ) ) : ?>
		<p class="shelfmark-search-term"><?php esc_html_e( 'Results for:', 'shelfmark' ); ?> <?php echo $_GET['q']; ?></p>
		<ul class="shelfmark-search-results" data-page="<?php echo $shelfmark_paged; ?>"></ul>
		<p class="shelfmark-search-page"><?php esc_html_e( 'Page', 'shelfmark' ); ?> <?php echo $shelfmark_paged; ?></p>
	<?php endif; ?>
</div>
