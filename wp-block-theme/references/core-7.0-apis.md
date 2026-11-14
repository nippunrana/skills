# Core APIs — collaboration, admin UX, security, media, speculative loading

Cross-cutting public APIs that don't belong to the theme, AI or WooCommerce reference files. Each section is the developer-facing surface only. Everything here is valid on WordPress 7.0 and 7.1.

**Load this file when**: the user asks about real-time collaboration, admin View Transitions, the Command Palette, password hashing / Argon2, image LCP / `fetchpriority`, image alt text import, or speculative loading (prefetch / prerender).

---

## 1. Real-time collaboration is not in core

Real-time collaborative editing was removed from WordPress 7.0 before release and is not enabled in 7.1. **Never** emit `wp_is_collaboration_enabled()`, `wp_is_collaboration_allowed()`, `WP_ALLOW_COLLABORATION`, or any `WP_Sync_*` / `WP_HTTP_Polling_Sync_Server` class — none of them exist, and calling them is a fatal error. A `function_exists()` guard around them is dead code; leave it out.

Post locking is still how core signals that someone is editing. When code must avoid overwriting an editor's work (a background job that rewrites post content or meta), check the lock:

```php
require_once ABSPATH . 'wp-admin/includes/post.php';

// User ID of whoever holds the edit lock, or false when nobody else is editing.
$editing_user_id = wp_check_post_lock( $post_id );

if ( false !== $editing_user_id ) {
	return; // Someone has this post open — try again later.
}
```

`wp_check_post_lock()` returns `false` when the post is unlocked **or** locked by the current user, so it answers "is someone *else* editing".

---

## 2. Admin UX: View Transitions & Command Palette

| API | Notes |
|---|---|
| `wp_enqueue_view_transitions_admin_css(): void` | Since 7.0. Enqueues the stylesheet that drives cross-document View Transitions between admin screens. Core enqueues it for wp-admin; call it only for a custom admin surface that should match. Respects the OS reduced-motion preference. |
| `wp_admin_bar_command_palette_menu( WP_Admin_Bar $wp_admin_bar ): void` | Since 7.0. Core callback on `admin_bar_menu` that renders the Command Palette trigger (⌘K / Ctrl+K). Don't call it; know it exists so you don't duplicate or remove it when customising the admin bar. |

---

## 3. Security: password hashing filters (Argon2)

Two filters (since 6.8) make the core `password_hash()` pipeline configurable.

| Filter | Notes |
|---|---|
| `wp_hash_password_algorithm` | Algorithm passed to `password_hash()` / `password_needs_rehash()`. Default `PASSWORD_BCRYPT`. Since 7.0 the value is always a string. |
| `wp_hash_password_options` | The `$options` array (e.g. `memory_cost`, `time_cost`, `threads` for Argon2). Receives the algorithm as its second argument. |

```php
/**
 * Switches password hashing to Argon2id where PHP supports it.
 */
function my_plugin_password_algorithm( string $algorithm ): string {
	// bcrypt is the only algorithm available on every PHP build — check before switching.
	return defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : $algorithm;
}
add_filter( 'wp_hash_password_algorithm', 'my_plugin_password_algorithm' );
```

> **Caveat:** Argon2 exists only when PHP was compiled with it. Always guard with `defined()` so a missing algorithm cannot break the login path. There is no wp-admin setting for the algorithm.

---

## 4. Media: LCP attributes and alt text

| API | Notes |
|---|---|
| `wp_get_loading_optimization_attributes( string $tag_name, array $attr, string $context ): array` | Since 6.3. Returns the `loading`, `decoding` and `fetchpriority` attributes core would choose. Use it when a dynamic block's `render_callback` prints its own `<img>` or `<iframe>`. |
| `wp_get_image_alttext( $file ): string` | Since 7.0. Reads alt text embedded in an image **file's** metadata. Defined in `wp-admin/includes/image.php`, so it is only loaded in admin requests. It is not a getter for an attachment's alt text — that is `get_post_meta( $attachment_id, '_wp_attachment_image_alt', true )`. |

```php
$optimized = wp_get_loading_optimization_attributes(
	'img',
	array(
		'width'  => 1200,
		'height' => 600,
	),
	'{{THEME_SLUG}}_hero'
);
// Merge $optimized into the <img> attributes.
```

> **Do not** call `wp_maybe_add_fetchpriority_high_attr()` — it is private.

---

## 5. Speculative loading (prefetch / prerender)

Core has printed speculation rules itself since 6.8: prefetch with conservative eagerness, for logged-out visitors on sites with pretty permalinks. **Never** hand-write a `<script type="speculationrules">` tag — it duplicates core's.

Change the behaviour with filters:

```php
/**
 * Prerenders links with moderate eagerness.
 */
function {{THEME_PREFIX}}_speculation_rules( ?array $config ): ?array {
	if ( null === $config ) {
		return null; // Core disabled it for this request — keep it disabled.
	}

	$config['mode']      = 'prerender';
	$config['eagerness'] = 'moderate';
	return $config;
}
add_filter( 'wp_speculation_rules_configuration', '{{THEME_PREFIX}}_speculation_rules' );

/**
 * Keeps cart and checkout URLs out of speculative loading.
 */
function {{THEME_PREFIX}}_speculation_exclude_paths( array $paths ): array {
	$paths[] = '/cart/*';
	$paths[] = '/checkout/*';
	return $paths;
}
add_filter( 'wp_speculation_rules_href_exclude_paths', '{{THEME_PREFIX}}_speculation_exclude_paths' );
```

| Key | Values |
|---|---|
| `mode` | `auto`, `prefetch`, `prerender` |
| `eagerness` | `auto`, `conservative`, `moderate`, `eager` |

Return `null` from the configuration filter to switch speculative loading off. Exclude any URL whose mere loading has a side effect (cart, checkout, logout, one-time links) before choosing `prerender`.

---

## 6. Classic scripts that need a script module

Since 7.0 a classic script can declare script-module dependencies with the `module_dependencies` key of its `$args` array. The script must be printed in the footer or use the `defer` strategy, otherwise core raises `_doing_it_wrong()` because the import map would not exist yet.

```php
wp_register_script(
	'{{THEME_SLUG}}-legacy-widget',
	get_theme_file_uri( 'assets/js/legacy-widget.js' ),
	array(),
	wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' ),
	array(
		'in_footer'           => true,
		'module_dependencies' => array( '@wordpress/interactivity' ),
	)
);
```

Inside the classic script, load the module with a dynamic `import()`.
