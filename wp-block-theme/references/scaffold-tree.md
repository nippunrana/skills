# Block Theme Scaffold Trees

Copy these layouts verbatim when starting a new theme, child theme, or dynamic block. Replace `{{THEME_SLUG}}` with the actual kebab-case slug, and `{{THEME_PREFIX}}` with its snake_case form (`my-theme` → `my_theme`) wherever a PHP function name needs a prefix.

> **PHP rule:** Every `.php` file containing active PHP logic (functions, classes, or `add_action`/`add_filter` calls) must begin with `<?php` followed immediately by `declare(strict_types=1);`. All named functions must have typed parameters and a return type (e.g. `function my_fn( string $arg ): void {}`). Callbacks passed to `add_action` / `add_filter` are named functions, not closures; a `render_callback` closure must also be typed. **Exception:** Pure-markup pattern files in `patterns/*.php` that contain only the block comment docblock header and HTML block markup — with no function or class definitions — are exempt from `declare(strict_types=1);` because strict_types has no effect without typed signatures. See `references/architecture.md → PHP Requirements` for the complete checklist and the PHP version step (default 8.4; 8.2 and 8.3 through `references/php-compatibility.md`).

## Parent block theme

```
{{THEME_SLUG}}/
├── style.css                           ← Theme metadata header (Theme Name, Version, Text Domain, Requires at least, Requires PHP)
├── theme.json                          ← Settings & global styles (use "version": 3, $schema: https://schemas.wp.org/wp/7.0/theme.json)
├── functions.php                       ← Pattern category + require_once for the inc/ files it loads
├── README.md                           ← Optional
│
├── templates/
│   ├── index.html                      ← Required: absolute fallback
│   ├── singular.html                   ← Posts + static pages
│   ├── page.html                       ← Static pages
│   ├── single.html                     ← Individual posts
│   ├── archive.html                    ← Archive views
│   ├── search.html                     ← Search results
│   ├── 404.html                        ← Error page
│   └── {custom-slug}.html              ← Custom templates registered in theme.json
│
├── parts/
│   ├── header.html
│   ├── footer.html
│   └── sidebar.html                    ← Optional
│
├── inc/
│   ├── pattern-assets.php              ← Pattern-asset registry + loader (references/architecture.md → Asset Pipeline)
│   ├── interactivity.php               ← Optional: adds data-wp-* directives to core blocks at render
│   ├── woocommerce.php                 ← Optional: registers the checkout script with WooCommerce (references/woocommerce.md → §6.4)
│   ├── class-{{THEME_SLUG}}-checkout-integration.php ← Optional: required only by woocommerce.php, never by functions.php
│   └── blocks/                         ← Optional: PHP-only dynamic blocks (see below)
│
├── patterns/
│   ├── {master-slug}.php               ← Master pattern (Inserter: false)
│   ├── {section-slug}.php              ← One PHP file per section
│   └── {section-slug}/
│       ├── style.css                   ← Scoped to .is-style-{section-slug}; listed in the registry
│       ├── index.js                    ← Optional non-reactive script; set 'script' => true in the registry
│       └── view.js                     ← Optional Interactivity API store; set 'module' => true in the registry
│
└── assets/
    ├── fonts/                          ← Local font files referenced from theme.json
    ├── images/                         ← Referenced via get_theme_file_uri( 'assets/images/…' )
    ├── js/                             ← Optional: scripts that belong to no pattern (checkout.js for WooCommerce)
    └── css/
        └── global-resets.css           ← Optional, enqueued via wp_enqueue_block_style
```

## Child block theme

When the child theme is the one being built, `{{THEME_SLUG}}` **is the child's slug** (for example `my-theme-child`) and `{{PARENT_THEME_SLUG}}` is the parent's.

```
{{THEME_SLUG}}/
├── style.css                           ← Must declare `Template: {{PARENT_THEME_SLUG}}`
├── theme.json                          ← Merges with parent; only the diff
├── functions.php                       ← Optional; child-specific code. If the child ships patterns with assets it has its own inc/pattern-assets.php, required with __DIR__
│
├── templates/                          ← Optional: copy of parent template to override
│   └── {slug}.html
│
├── parts/                              ← Optional: same idea
│
├── inc/                                ← Optional: pattern-assets.php when the child ships patterns with assets
│
├── patterns/                           ← Child-specific patterns; slug prefix {{THEME_SLUG}}/
│   ├── {pattern}.php
│   └── {pattern}/style.css             ← Optional: listed in the child's own registry
│
└── assets/
    └── images/
```

**Notes on child themes:**
- Arrays in `theme.json` (`palette`, `fontFamilies`, `fontSizes`, `spacingSizes`) overwrite the parent rather than merge. Repeat every parent entry the child still needs, then add its own. `defaultPalette` only controls WordPress's built-in colors; it does not bring parent colors back.
- `style.css` must include the parent's `Template:` header so WordPress knows which theme to inherit from.

## Companion plugin

For WooCommerce code that the Placement gate sends to a plugin (`references/woocommerce.md → §2`). Here `{{THEME_SLUG}}`, `{{THEME_PREFIX}}` and `{{TEXT_DOMAIN}}` stand for the **plugin's** slug, prefix and text domain.

```
{{THEME_SLUG}}/
├── {{THEME_SLUG}}.php                  ← Plugin header (Plugin Name, Version, Requires at least, Requires PHP, Requires Plugins: woocommerce, Text Domain) + require_once for the inc/ files it loads
├── inc/
│   ├── checkout-fields.php             ← Optional: additional checkout fields (references/woocommerce-checkout-fields.md)
│   ├── woocommerce.php                 ← Optional: registers the checkout script (references/woocommerce.md → §6.4)
│   └── class-{{THEME_SLUG}}-checkout-integration.php ← Required only by woocommerce.php
└── assets/
    └── js/
        └── checkout.js                 ← Optional: observers, fills and filters that belong to the plugin
```

The PHP rule at the top of this file applies unchanged. The main file requires its `inc/` files with `require_once __DIR__ . '/inc/…';`.

## Dynamic block (WP 7.0 `autoRegister`)

A dynamic block registered with `'autoRegister' => true` does not need any `block.json` or editor JavaScript. Everything lives in PHP.

```
{{THEME_SLUG}}/
└── inc/
    └── blocks/
        ├── {block-slug}.php             ← register_block_type() call lives here
        └── {block-slug}/                ← Only when the block needs its own assets
            ├── style.css                ← Enqueued with wp_enqueue_block_style()
            └── view.js                  ← Interactivity API store, bound with view_script_module_ids
```

Then in `functions.php`:

```php
require_once __DIR__ . '/inc/blocks/{block-slug}.php';
```

A dynamic block's own stylesheet belongs to the block type, so `wp_enqueue_block_style( '{{THEME_SLUG}}/{block-slug}', array( 'handle' => …, 'src' => …, 'path' => … ) )` is the right call — core loads it wherever the block is used. Its Interactivity script module is bound with `view_script_module_ids` on `register_block_type()`. Worked example: `references/html-conversion.md → Interactivity API (reactive logic)`.
