<?php
/**
 * Loads the Shelfmark fixture against minimal WordPress stubs and checks, by running it,
 * that the code which only looks unused is reached and that the planted dead code is not.
 *
 *   php wp-smoke.php <plugin-dir> [after-cleanup]
 *
 * Prints one line per check and exits 1 if any fails. With "after-cleanup" the checks that
 * a planted dead item exists are skipped, so the same script proves that the plugin still
 * works once all planted dead code is gone.
 *
 * This is a harness self-test. It is not part of any eval run.
 */

$dir   = rtrim( $argv[1] ?? '', '/' ) . '/';
$after = ( $argv[2] ?? '' ) === 'after-cleanup';
if ( ! is_file( $dir . 'shelfmark.php' ) ) {
	fwrite( STDERR, "usage: php wp-smoke.php <plugin-dir> [after-cleanup]\n" );
	exit( 2 );
}

$GLOBALS['smoke'] = array(
	'hooks'      => array(),
	'shortcodes' => array(),
	'routes'     => array(),
	'patterns'   => array(),
	'post_types' => array(),
	'assets'     => array(),
	'pages'      => array(),
	'options'    => array(),
	'transients' => array(),
	'failed'     => 0,
);

function smoke_check( $label, $ok ) {
	echo ( $ok ? 'ok    ' : 'WRONG ' ) . $label . "\n";
	if ( ! $ok ) {
		$GLOBALS['smoke']['failed']++;
	}
}

// --- WordPress stubs: only what the fixture calls. ---
define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );

class WP_Error {
	public $code;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code = $code;
	}
}

class WP_REST_Request implements ArrayAccess {
	private $params;
	public function __construct( array $params = array() ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}
	public function offsetExists( $offset ): bool {
		return isset( $this->params[ $offset ] );
	}
	public function offsetGet( $offset ): mixed {
		return $this->params[ $offset ] ?? null;
	}
	public function offsetSet( $offset, $value ): void {
		$this->params[ $offset ] = $value;
	}
	public function offsetUnset( $offset ): void {
		unset( $this->params[ $offset ] );
	}
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['smoke']['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['smoke']['hooks'][ $hook ][] = $callback;
}
function register_activation_hook( $file, $callback ) {
	$GLOBALS['smoke']['hooks']['activate'][] = $callback;
}
function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['smoke']['hooks']['deactivate'][] = $callback;
}
function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	return 'https://shop.test/wp-content/plugins/shelfmark/';
}
function is_admin() {
	return true;
}
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function esc_html_e( $text, $domain = '' ) {
	echo $text;
}
function _n( $single, $plural, $number, $domain = '' ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text );
}
function esc_url( $url ) {
	return (string) $url;
}
function esc_url_raw( $url ) {
	return (string) $url;
}
function wp_kses_post( $html ) {
	return (string) $html;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function wp_unslash( $value ) {
	return $value;
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, $decimals );
}
function checked( $value ) {
	echo $value ? 'checked' : '';
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function register_post_type( $name, $args = array() ) {
	$GLOBALS['smoke']['post_types'][] = $name;
}
function add_shortcode( $tag, $callback ) {
	$GLOBALS['smoke']['shortcodes'][ $tag ] = $callback;
}
function shortcode_atts( $defaults, $atts, $tag = '' ) {
	return array_merge( $defaults, (array) $atts );
}
function register_block_pattern( $name, $args ) {
	$GLOBALS['smoke']['patterns'][ $name ] = $args;
}
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['smoke']['routes'][ $route ] = $args;
}
function rest_ensure_response( $data ) {
	return $data;
}
function add_options_page( $page_title, $menu_title, $capability, $slug, $callback ) {
	$GLOBALS['smoke']['pages'][ $slug ] = $callback;
}
function wp_enqueue_style( $handle, $src = '' ) {
	$GLOBALS['smoke']['assets'][] = $src;
}
function wp_enqueue_script( $handle, $src = '' ) {
	$GLOBALS['smoke']['assets'][] = $src;
}
function wp_enqueue_script_module( $handle, $src = '' ) {
	$GLOBALS['smoke']['assets'][] = $src;
}
function wp_localize_script( $handle, $name, $data ) {
	return true;
}
function admin_url( $path = '' ) {
	return 'https://shop.test/wp-admin/' . $path;
}
function wp_create_nonce( $action ) {
	return 'nonce-' . $action;
}
function is_singular( $types = '' ) {
	return true;
}
function get_post_type() {
	return 'shelfmark_book';
}
function get_posts( $args = array() ) {
	return array( (object) array( 'ID' => 7 ), (object) array( 'ID' => 8 ) );
}
function get_permalink( $post = null ) {
	return 'https://shop.test/books/sample/';
}
function get_the_title( $post = null ) {
	return 'Sample Book';
}
function get_the_excerpt( $post = null ) {
	return 'A short summary.';
}
function get_the_ID() {
	return 7;
}
function get_post_meta( $post_id, $key = '', $single = false ) {
	$values = array(
		'_shelfmark_price'  => '12.5',
		'_shelfmark_rating' => '4',
		'_shelfmark_stock'  => '3',
	);
	return $values[ $key ] ?? '';
}
function get_option( $name, $default = false ) {
	return $GLOBALS['smoke']['options'][ $name ] ?? $default;
}
function get_transient( $key ) {
	return $GLOBALS['smoke']['transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl = 0 ) {
	$GLOBALS['smoke']['transients'][ $key ] = $value;
	return true;
}
function flush_rewrite_rules() {
}
function wp_next_scheduled( $hook ) {
	return false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
	$GLOBALS['smoke']['scheduled'][] = $hook;
}

function smoke_fire( $hook ) {
	foreach ( $GLOBALS['smoke']['hooks'][ $hook ] ?? array() as $callback ) {
		call_user_func( $callback );
	}
}
function smoke_render( callable $producer ) {
	ob_start();
	$returned = $producer();
	return ob_get_clean() . ( is_string( $returned ) ? $returned : '' );
}

// --- Load and run. ---
require $dir . 'shelfmark.php';

$missing = array();
foreach ( $GLOBALS['smoke']['hooks'] as $hook => $callbacks ) {
	foreach ( $callbacks as $callback ) {
		if ( ! is_callable( $callback ) ) {
			$missing[] = $hook . ' -> ' . ( is_string( $callback ) ? $callback : 'callable' );
		}
	}
}
smoke_check( 'every registered hook callback exists' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ), array() === $missing );
smoke_check(
	'callbacks built at runtime are registered for books and authors',
	in_array( 'shelfmark_save_book_meta', $GLOBALS['smoke']['hooks']['save_post_shelfmark_book'] ?? array(), true )
	&& in_array( 'shelfmark_save_author_meta', $GLOBALS['smoke']['hooks']['save_post_shelfmark_author'] ?? array(), true )
);

foreach ( array( 'activate', 'init', 'rest_api_init', 'admin_menu', 'wp_enqueue_scripts', 'admin_enqueue_scripts', 'shelfmark_daily_sync' ) as $hook ) {
	smoke_fire( $hook );
}

smoke_check( 'the daily sync is scheduled on activation', in_array( 'shelfmark_daily_sync', $GLOBALS['smoke']['scheduled'] ?? array(), true ) );
smoke_check( 'three post types are registered', 3 === count( array_unique( $GLOBALS['smoke']['post_types'] ) ) );

$with_ratings    = smoke_render( fn() => call_user_func( $GLOBALS['smoke']['shortcodes']['shelfmark_books'], array( 'heading' => 'Picks' ) ) );
$without_ratings = smoke_render( fn() => call_user_func( $GLOBALS['smoke']['shortcodes']['shelfmark_books'], array( 'ratings' => 'no' ) ) );
smoke_check( 'the book list view uses the heading set by the shortcode', str_contains( $with_ratings, '<h2>Picks</h2>' ) );
smoke_check( 'the book list view uses the ratings switch set by the shortcode', str_contains( $with_ratings, 'shelfmark-stars' ) && ! str_contains( $without_ratings, 'shelfmark-stars' ) );
smoke_check( 'the book list calls the summary toggle from inline markup', str_contains( $with_ratings, 'onclick="shelfmarkToggleSummary(this)"' ) );
smoke_check( 'the search shortcode renders its view', str_contains( smoke_render( fn() => call_user_func( $GLOBALS['smoke']['shortcodes']['shelfmark_search'] ) ), 'shelfmark-search' ) );

$template = call_user_func( $GLOBALS['smoke']['hooks']['template_include'][0], '/theme/single.php' );
smoke_check( 'single books use the template chosen by file name', str_ends_with( $template, 'templates/single-book.php' ) );

$pattern = $GLOBALS['smoke']['patterns']['shelfmark/book-grid']['content'] ?? '';
smoke_check( 'the pattern file is loaded by name and keeps its block markup', str_contains( $pattern, '<!-- wp:group' ) && str_contains( $pattern, '[shelfmark_books' ) );

$routes = $GLOBALS['smoke']['routes'];
smoke_check( 'the three REST routes are registered with existing callbacks', 3 === count( $routes ) && array() === array_filter( $routes, fn( $route ) => ! is_callable( $route['callback'] ) ) );
$books = call_user_func( $routes['/books']['callback'], new WP_REST_Request( array( 'per_page' => 10 ) ) );
smoke_check( 'the public books route returns books', is_array( $books ) && 'Sample Book' === ( $books[0]['title'] ?? '' ) );

$_GET = array();
$general = smoke_render( fn() => call_user_func( $GLOBALS['smoke']['pages']['shelfmark'] ) );
$_GET = array( 'tab' => 'import' );
$import = smoke_render( fn() => call_user_func( $GLOBALS['smoke']['pages']['shelfmark'] ) );
$_GET = array();
smoke_check( 'the settings page includes each tab file by name', str_contains( $general, 'shelfmark-settings-form' ) && str_contains( $import, 'shelfmark-import-form' ) );

smoke_check( 'the documented template tag exists and prints', function_exists( 'shelfmark_the_availability' ) && str_contains( smoke_render( 'shelfmark_the_availability' ), '3 copies in stock' ) );

$cache = new \Shelfmark\Cache( 'smoke' );
$calls = 0;
$first = $cache->remember( array( 'a' => 1 ), function () use ( &$calls ) {
	$calls++;
	return 'value';
} );
$second = $cache->remember( array( 'a' => 1 ), function () use ( &$calls ) {
	$calls++;
	return 'other';
} );
smoke_check( 'the cache remembers a value', 'value' === $first && 'value' === $second && 1 === $calls );

$assets = implode( ' ', $GLOBALS['smoke']['assets'] );
smoke_check( 'the front-end and admin assets are enqueued', str_contains( $assets, 'summary-toggle.js' ) && str_contains( $assets, 'frontend.js' ) && str_contains( $assets, 'admin.js' ) && str_contains( $assets, 'frontend.css' ) );

// Planted dead code: it must not be reachable.
smoke_check( 'the ISBN helpers are never loaded', ! function_exists( 'shelfmark_isbn10_to_isbn13' ) && ! function_exists( 'shelfmark_isbn_checksum' ) );
smoke_check( 'the carousel script is never enqueued', ! str_contains( $assets, 'carousel' ) );
smoke_check( 'the commented-out sync function is not defined', ! function_exists( 'shelfmark_sync_v1_fetch' ) );
if ( ! $after ) {
	smoke_check( 'before cleanup: the planted ISBN file and carousel script are present', is_file( $dir . 'includes/isbn.php' ) && is_file( $dir . 'assets/js/carousel.js' ) );
	smoke_check( 'before cleanup: the unused private method is present', ( new ReflectionClass( '\Shelfmark\Cache' ) )->hasMethod( 'normalize_key' ) );
}

echo "\nwp-smoke: " . ( 0 === $GLOBALS['smoke']['failed'] ? 'all ok' : $GLOBALS['smoke']['failed'] . ' wrong' ) . "\n";
exit( 0 === $GLOBALS['smoke']['failed'] ? 0 : 1 );
