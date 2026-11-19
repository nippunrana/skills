<?php
/**
 * General settings tab.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shelfmark_settings = wp_parse_args(
	get_option( 'shelfmark_settings', array() ),
	array(
		'import_source' => '',
		'show_prices'   => true,
	)
);
?>
<form id="shelfmark-settings-form">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="shelfmark-import-source"><?php esc_html_e( 'Cover metadata source', 'shelfmark' ); ?></label></th>
			<td><input type="url" class="regular-text" id="shelfmark-import-source" name="import_source" value="<?php echo esc_attr( $shelfmark_settings['import_source'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Prices', 'shelfmark' ); ?></th>
			<td><label><input type="checkbox" name="show_prices" value="1" <?php checked( $shelfmark_settings['show_prices'] ); ?>> <?php esc_html_e( 'Show prices in book lists', 'shelfmark' ); ?></label></td>
		</tr>
	</table>
	<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'shelfmark' ); ?></button></p>
</form>
