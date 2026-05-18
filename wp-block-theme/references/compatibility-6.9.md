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
- **`'autoRegister' => true`** on `register_block_type()`: new in 7.0. Register dynamic blocks the long way (`block.json` + `render.php`).
- **WP AI Client / Settings → Connectors**: not present on 6.9. Skip any UI that depends on the AI Client.
