# WordPress 6.9 Compatibility Reference
*Standard: WordPress 6.9.x (Stable Standard - Early 2026)*

Use this guide when the user specifically mentions they are on WordPress 6.9 or lower, or if the 7.0 features (Icons Registry, PHP-only Blocks) are not rendering as expected.

## Summary of Fallback Patterns

| Feature | WP 7.0 Elite Pattern | WP 6.9 Fallback Pattern |
|---|---|---|
| **theme.json** | Version 4 (Schema 7.0) | Version 3 (Schema 6.6) |
| **Complex Sections** | Native PHP-only Blocks | `<!-- wp:html -->` wrappers |
| **Icons** | `WP_Icons_Registry` | PHP helper (e.g. `{{THEME_SLUG}}_get_icon`) |
| **Templates** | `is_ai_ready` / PHP Registration | `customTemplates` in `theme.json` |
| **Metadata** | `intent` AI tags | Standard keywords / PHP comments |
| **Dashboards** | `dataviews.json` | Default Site Editor views |

---

## 1. theme.json (Version 3)

In 6.9, use **Version 3**. The Version 4 schema may cause WordPress to ignore the file.

```json
{
  "$schema": "https://schemas.wp.org/wp/6.6/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true
  }
}
```

## 2. Converting Complex HTML (The wp:html Fallback)

Since 6.9 does not support native PHP-only blocks (which map `block.json` to PHP), you must use the `wp:html` comment wrapper for any design elements that cannot be mapped to core blocks.

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

## 3. Dynamic Icons (PHP Helpers)

The native `core/icon` block is not available or lacks the registry in 6.9. Use a PHP helper function to inject SVGs.

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

Supported in 6.6+. Use the same `__experimentalRole: content` attribute in your block markup.

```html
<!-- wp:paragraph {"__experimentalRole":"content"} -->
<p>This text is overridable in 6.9.4.</p>
<!-- /wp:paragraph -->
```

## 6. What to Skip

*   **Abilities API**: Do not register block abilities or intents. They will be ignored.
*   **Data Views**: Do not create `dataviews.json`. Site-level data management must be done via standard admin screens.
*   **Speculation Rules (Complex)**: Use basic `prerender` rules; avoid the multi-step dynamic injection logic which may be too heavy for 6.9.x cores.
