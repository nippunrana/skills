<?php
/**
 * Cover import tab.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form id="shelfmark-import-form">
	<p><?php esc_html_e( 'Paste a metadata URL from your distributor to look up a cover image.', 'shelfmark' ); ?></p>
	<p>
		<label for="shelfmark-cover-url" class="screen-reader-text"><?php esc_html_e( 'Metadata URL', 'shelfmark' ); ?></label>
		<input type="url" class="regular-text" id="shelfmark-cover-url" name="cover_url" required>
		<button type="submit" class="button"><?php esc_html_e( 'Look up cover', 'shelfmark' ); ?></button>
	</p>
	<p id="shelfmark-import-result" role="status"></p>
</form>
