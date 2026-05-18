# WordPress 6.9 Compatibility Reference
*Use only when the user explicitly states they are on WordPress 6.9 or earlier, or when WP 7.0 features (the `core/icon` block, the `'autoRegister'` flag on `register_block_type()`, the Abilities API) are not available in the target environment.*

The default target for the skill is WP 7.0. Do not load this file by default — substitute these fallbacks only when 7.0 APIs are unavailable.

## Summary of Fallback Patterns

| Feature | WP 7.0 pattern | WP 6.9 fallback |
|---|---|---|
| **theme.json** | `"version": 3` + `https://schemas.wp.org/trunk/theme.json` | Same — `"version": 3` + `https://schemas.wp.org/wp/6.6/theme.json` |
| **Complex sections** | Dynamic block via `register_block_type()` with `'supports' => ['autoRegister' => true]` + `render_callback` | `register_block_type()` still works, but `autoRegister` is unavailable — register a `block.json` + `render.php` pair, or use `<!-- wp:html -->` wrappers |
| **Icons** | `core/icon` block + Icon Registration API | PHP helper (e.g. `{{THEME_SLUG}}_get_icon`) |
| **Templates** | `register_block_template()` (also available in 6.7+) | Same, or `customTemplates` in `theme.json` |
| **Abilities API** | `wp_register_ability()` on `wp_abilities_api_init` | Not available — skip; do not call these functions |
| **Pattern overrides syntax** | `metadata.bindings` + `metadata.name` (works on any block) | Same syntax (the `__experimentalRole` form was the pre-6.6 experiment and should not be used) |
| **Viewport block visibility** | `metadata.blockVisibility.viewport` on block metadata | Not available. Use CSS `@media` queries with scoped `.is-style-*` selectors instead (`display: none` at the appropriate breakpoint). |
| **`contentOnly` default for unsynced patterns** | Default in WP 7.0 — no explicit `templateLock` needed. Opt-out via `"disableContentOnlyForUnsyncedPatterns": true`. | NOT default on 6.9. Must explicitly set `"templateLock": "contentOnly"` on unsynced patterns where that behaviour is required. `disableContentOnlyForUnsyncedPatterns` has no effect on 6.9 (the feature it opts out of doesn't exist there). |
| **`core/headings` block** | `core/headings` with H1–H6 level variations | Not available. Use `core/heading` with a fixed `level` attribute. |
| **`core/navigation-overlay-close`** | Native mobile nav close block | Not available. Render a close button via a dynamic block's `render_callback`. |
| **`watch()` / `data-wp-watch`** | Interactivity API reactive signal subscriptions | Available from WP 6.9.4+ — no fallback needed. |
| **`@wordpress/boot`** | Custom Site Editor pages with route validation | Not available. Use `add_menu_page()` for custom admin pages on 6.9. |
| **Block Hooks across all CPTs** | Auto-expanded scope via REST controller (WP 7.0) | On 6.9, Block Hooks only fire reliably for `post` and `page`. For CPTs, add a manual `hooked_block_types` filter targeting the CPT explicitly. |
| **`block_bindings_supported_attributes` filter** | Pattern Overrides for any custom block | WP 7.0+ only. On 6.9, Pattern Overrides are limited to core blocks that natively support `metadata.bindings`. |
| **Template / Template Part / Pattern Revisions** | Revisions panel in Site Editor (WP 7.0) | Not available on 6.9. "Clear Customisations" is the only rollback option. |
| **Button pseudo-element states in `theme.json`** | `:hover`, `:focus`, `:active`, `:focus-visible` under `styles.blocks.core/button` | Not available on 6.9. Style button states via scoped CSS in a block style variation stylesheet instead. |
| **Preset dimension values (`settings.dimensions`)** | `aspectRatios` in `theme.json settings` | Not available on 6.9. Omit `settings.dimensions`; apply aspect ratios via scoped CSS. |
| **`wp_get_image_alttext()`** | Returns alt text from attachment meta | Not available on 6.9. Use `get_post_meta( $attachment_id, '_wp_attachment_image_alt', true )` instead. |
| **Font Library for all themes** | Universal Font Library UI (all theme types) | On 6.9 with classic or hybrid themes, the Font Library is not available. Register fonts via `wp_enqueue_style()` and `@font-face` in `style.css`. |
| **Iframed editor enforcement (Block API v3)** | Enforced when all blocks are API v3 | On 6.9, the iframed editor is opt-in per-block. Do not depend on iframed-editor enforcement. |

---

## 1. theme.json schema

Same shape as WP 7.0 — use `"version": 3`. Pin the schema URL to 6.6 if you want stability:

```json
{
  "$schema": "https://schemas.wp.org/wp/6.6/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true
  }
}
```

## 2. Complex sections without `autoRegister`

WP 7.0 lets `register_block_type()` register a block from PHP alone using `'supports' => ['autoRegister' => true]`. On 6.9, register the block the long way (a `block.json` next to a `render.php` callback), or as a last resort wrap raw HTML in a `<!-- wp:html -->` comment block.

**`patterns/my-complex-section.php`**
```php
<!-- wp:group {"className":"is-style-my-complex-section"} -->
<div class="wp-block-group is-style-my-complex-section">
    <!-- wp:html -->
    <div class="custom-layout-from-html">
        <div class="glass-header">...</div>
        <!-- Your raw HTML here -->
    </div>
    <!-- /wp:html -->
</div>
<!-- /wp:group -->
```

## 3. Dynamic Icons (PHP helpers)

The `core/icon` block and Icon Registration API are new in 7.0. On 6.9, register a PHP helper and emit SVGs directly in pattern files.

**`patterns/header-top-bar.php`**
```php
<div class="top-bar-icon">
    <?php 
    if ( function_exists('{{THEME_SLUG}}_get_icon') ) {
        echo {{THEME_SLUG}}_get_icon('warning'); 
    }
    ?>
</div>
```

## 4. Asset Enqueuing (6.9 Style)

The **Script Modules API** and **Interactivity API** are fully supported in 6.9.4. Keep using `wp_register_script_module()` and `register_block_style()` as described in `architecture.md`.

## 5. Synced Pattern Overrides

Supported since 6.6 with the same syntax used in WP 7.0: `metadata.bindings` pointing at `core/pattern-overrides`, plus a stable `metadata.name`. See the Synced Pattern Overrides section of `architecture.md`. The pre-6.6 experimental `__experimentalRole` form was removed and should not be used.

## 6. What to skip on 6.9

- **Abilities API** (`wp_register_ability`, `wp_register_ability_category`): not available — these functions ship with 7.0. Do not call them.
- **`core/breadcrumbs` and `core/icon`**: new in 7.0. Use a PHP helper for breadcrumbs (e.g. `{{THEME_SLUG}}_breadcrumbs()`) and SVG helpers for icons.
- **`core/headings` and `core/navigation-overlay-close`**: new in 7.0. Use `core/heading` (fixed level) and a dynamic block close button respectively.
- **`'autoRegister' => true`** on `register_block_type()`: new in 7.0. Register dynamic blocks the long way (`block.json` + `render.php`).
- **WP AI Client / Settings → Connectors**: not present on 6.9. Skip any UI that depends on the AI Client.
- **`@wordpress/boot` and `@wordpress/grid`**: new in 7.0. Not available on 6.9.
- **`block_bindings_supported_attributes` filter**: new in 7.0. Pattern Overrides for custom blocks not available on 6.9.
- **`metadata.blockVisibility.viewport`**: new in 7.0. Use CSS media queries with scoped `.is-style-*` selectors on 6.9.

## 7. PHP 8.3 on 6.9 Sites

PHP 8.3 syntax (`declare(strict_types=1)`, typed parameters, return types, `match`, `??`, `str_contains()`, `?->`) is fully compatible with WordPress 6.9. No PHP-level fallbacks are needed for 6.9 sites running PHP 8.3. Apply all PHP 8.3 rules from `references/architecture.md → PHP 8.3 Requirements` regardless of the WP version.
