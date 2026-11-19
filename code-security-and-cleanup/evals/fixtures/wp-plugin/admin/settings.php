<?php
/**
 * Settings screen.
 *
 * @package Shelfmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'shelfmark_add_settings_page' );

/**
 * Add the settings page under Settings.
 */
function shelfmark_add_settings_page() {
	add_options_page(
		__( 'Shelfmark', 'shelfmark' ),
		__( 'Shelfmark', 'shelfmark' ),
		'manage_options',
		'shelfmark',
		'shelfmark_render_settings_page'
	);
}

/**
 * Print the settings page with its tabs.
 */
function shelfmark_render_settings_page() {
	$tab = isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : 'general';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Shelfmark', 'shelfmark' ); ?></h1>
		<nav class="nav-tab-wrapper">
			<a class="nav-tab" href="<?php echo esc_url( admin_url( 'options-general.php?page=shelfmark&tab=general' ) ); ?>"><?php esc_html_e( 'General', 'shelfmark' ); ?></a>
			<a class="nav-tab" href="<?php echo esc_url( admin_url( 'options-general.php?page=shelfmark&tab=import' ) ); ?>"><?php esc_html_e( 'Import', 'shelfmark' ); ?></a>
		</nav>
		<?php include SHELFMARK_DIR . 'admin/tabs/' . $tab . '.php'; ?>
	</div>
	<?php
}
