# FSE Architecture Reference — WordPress Block Themes
*Standard: WordPress 7.0 Enterprise (May 2026)*

## Table of Contents
1. [Theme Initialization Requirements](#theme-initialization-requirements)
2. [Directory Structure](#directory-structure)
3. [Template Hierarchy & Fallback Logic](#template-hierarchy--fallback-logic)
4. [Block Markup Rules](#block-markup-rules)
5. [Native-First Conversion & Block Mapping](#native-first-conversion--block-mapping)
6. [Block Locking (Client-Proofing)](#block-locking-client-proofing)
7. [Block Bindings API (Dynamic Data)](#block-bindings-api-dynamic-data)
8. [Interactivity API (Modern JS)](#interactivity-api-modern-js)
9. [Accessibility (A11y) Standards](#accessibility-a11y-standards)
10. [PHP Pattern Rules](#php-pattern-rules)
11. [Asset Pipeline (functions.php)](#asset-pipeline-functionsphp)
12. [Site Editor Sync Behaviour](#site-editor-sync-behaviour)
13. [Child Theme Rules](#child-theme-rules)

---

## Theme Initialization Requirements

A theme MUST have all three of the following to appear in the WordPress dashboard:

| File | Purpose |
|---|---|
| `style.css` | Theme metadata (Theme Name, Version, Author, etc.) |
| `theme.json` | Global settings and styles engine |
| `templates/index.html` | Absolute fallback template — required for theme validity |

`functions.php` is optional in a pure block theme but almost always needed for enqueueing custom
assets, registering pattern categories, and adding editor styles.

---

## Directory Structure

```
theme-name/
├── style.css                     ← Theme metadata header
├── theme.json                    ← Global settings & styles
├── functions.php                 ← Optional: asset enqueueing, hooks
│
├── templates/                    ← Page-level view templates
│   ├── index.html                ← Required: absolute fallback
│   ├── singular.html             ← Posts + static pages (unified)
│   ├── single.html               ← Individual posts only
│   ├── page.html                 ← Static pages only
│   ├── archive.html              ← Archive/category views
│   ├── search.html               ← Search results
│   ├── 404.html                  ← Error page
│   └── {custom-slug}.html        ← Custom templates (registered in theme.json)
│
├── parts/                        ← Reusable template parts
│   ├── header.html
│   ├── footer.html
│   └── sidebar.html
│
├── patterns/                     ← Block patterns (PHP files)
│   └── my-section.php
│
└── assets/
    ├── fonts/
    ├── images/
    └── css/
```

For **modular templates** (custom landing pages), co-locate assets in the patterns directory:

```
templates/
  my-landing-page.html            ← Template entry point
patterns/
  my-landing-page.php             ← Master pattern (assembler)
  my-section.php                  ← Sub-pattern
  my-section/
    style.css                     ← Scoped CSS for sub-pattern
    index.js                      ← Template-specific JS
```

---

## Template Hierarchy & Fallback Logic

WordPress resolves which template to use by walking this chain (first match wins):

```
Custom Template (page attribute) 
  → front-page.html 
  → home.html 
  → page-{slug}.html 
  → page-{ID}.html 
  → page.html 
  → singular.html 
  → index.html
```

### Template Registration API (WP 7.0+)

For enterprise themes, register templates in PHP instead of `theme.json` to enable A/B testing, AI-driven variations, and dynamic segments:

```php
add_action( 'init', function() {
    register_block_template( 'egnitech-one-child//my-landing-page', array(
        'title'       => __( 'Elite Landing Page', 'egnitech-one-child' ),
        'description' => __( 'A high-performance landing page template.', 'egnitech-one-child' ),
        'post_types'  => array( 'page' ),
        'is_ai_ready' => true, // Enables Abilities API integration
    ) );
} );
```

For archives:
```
category-{slug}.html → category-{ID}.html → category.html → archive.html → index.html
```

For search:
```
search.html → index.html
```

**Custom templates** (created via `customTemplates` in `theme.json`) appear in the Page
Attributes panel and bypass the hierarchy when explicitly assigned.

---

## Block Markup Rules

### The Golden Rule

Every piece of raw HTML inside a template or pattern MUST be wrapped in block comment tags.
WordPress strips any content outside recognized block boundaries when saving through the editor.

```html
<!-- Correct: raw HTML wrapped in Native PHP-only Block -->
<!-- wp:my-theme/legacy-section -->
<section class="hero">
  <h1>Welcome</h1>
</section>
<!-- /wp:my-theme/legacy-section -->

<!-- Correct: native WP block — no wrapper needed -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Welcome</h1>
<!-- /wp:heading -->

<!-- WRONG: raw HTML outside any block wrapper — will be stripped -->
<section class="hero">
  <h1>Welcome</h1>
</section>
```

### Common Block Syntax Reference

```html
<!-- Pattern reference -->
<!-- wp:pattern {"slug":"theme-slug/pattern-name"} /-->

<!-- Post content (required in all page templates) -->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->

<!-- Group block (div wrapper) -->
<!-- wp:group {"tagName":"section","className":"my-section"} -->
<section class="wp-block-group my-section">
  <!-- inner blocks -->
</section>
<!-- /wp:group -->

<!-- Template part reference -->
<!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->

<!-- Image block -->
<!-- wp:image {"id":123,"sizeSlug":"full"} -->
<figure class="wp-block-image size-full">
  <img src="..." alt="..." />
</figure>
<!-- /wp:image -->

<!-- Query loop (for archive/search templates) -->
<!-- wp:query {"queryId":1,"query":{"inherit":true}} -->
<div class="wp-block-query">
  <!-- wp:post-template -->
    <!-- wp:post-title /-->
    <!-- wp:post-excerpt /-->
  <!-- /wp:post-template -->
</div>
<!-- /wp:query -->
```

### Block Attributes JSON

Block attributes go in the opening comment, not as HTML attributes:

```html
<!-- wp:group {
  "tagName": "section",
  "align": "full",
  "className": "hero-section",
  "style": {"spacing": {"padding": {"top": "4rem", "bottom": "4rem"}}},
  "lock": {"move": true, "remove": true}
} -->
```

---

## Native-First Conversion & Block Mapping

Top 1% developers avoid "Black Boxes." Instead of wrapping raw HTML, map your design to Core Blocks or **Native PHP-only Blocks**.

| HTML Tag | Core Block | Attributes to use |
|---|---|---|
| `<div>` (wrapper) | `core/group` | `tagName: "div"`, `layout: { "type": "constrained" }` |
| `<section>` | `core/group` | `tagName: "section"` |
| `<h1>` - `<h6>` | `core/heading` | `level: 1-6` |
| `<p>` | `core/paragraph` | |
| `<ul>` / `<ol>` | `core/list` | |
| `<img>` | `core/image` | |
| `SVG Icon` | `core/icon` | Register in **WP_Icons_Registry** |
| `2-3 Columns` | `core/columns` | Use **Native Grid** for complex layouts |

**Why?** This allows the Site Editor to control typography, colors, and spacing natively, making the theme much more valuable to the client.

---

## Block Locking (Client-Proofing)

Prevent clients from accidentally deleting or moving critical structural elements.

```json
"lock": {
  "move": true,
  "remove": true
}
```

- `move`: Prevents the block from being dragged to a different position.
- `remove`: Prevents the block from being deleted.

Apply this to the outermost wrapper of your Patterns and Template Parts.

### Content-Only Locking (Phase 3 Standard)

For patterns that allow multi-user collaboration in 2026, use `contentOnly` locking. This prevents users from moving blocks or changing layouts while allowing them to edit all text and images.

```json
"templateLock": "contentOnly"
```

This is the gold standard for "Client-Safe" patterns in WordPress 7.0.

---

## Block Bindings API (Dynamic Data)

Introduced in WP 6.5, this allows you to connect Core Blocks to dynamic data without custom PHP patterns.

```html
<!-- wp:paragraph {
  "metadata": {
    "bindings": {
      "content": {
        "source": "core/post-meta",
        "args": { "key": "my_custom_field" }
      }
    }
  }
} -->
<p>Fallback content shown if meta is empty.</p>
<!-- /wp:paragraph -->

### Icon Registry (Elite 2026 Standard)

Instead of custom PHP bindings or raw SVGs, register your theme's icon library into the native `WP_Icons_Registry`. This provides a searchable UI in the Site Editor.

```php
add_action( 'init', function() {
    $registry = WP_Icons_Registry::get_instance();
    $registry->register( 'my-theme/warning', array(
        'label' => __( 'Warning Icon', 'my-theme' ),
        'svg'   => '<svg>...</svg>',
    ) );
} );
```

### UI Support (Elite 2026 Standard)

To make custom bindings (Post Meta, Site Data) visible in the Site Editor's sidebar, you must register the source in PHP:

```php
add_action( 'init', function() {
    register_block_bindings_source( 'my-theme/custom-source', array(
        'label'              => __( 'Custom Source', 'my-theme' ),
        'get_value_callback' => 'my_theme_get_binding_value',
        'uses_context'       => array( 'postId' ),
    ) );
} );
```

Supported sources: `core/post-meta`, `core/site-title`, `core/site-tagline`.

---

## AI Abilities API (AI Editorial intents)

In WordPress 7.0, themes register "AI Abilities" to provide premium editorial tools (Tone Shift, Summarize) for specific patterns.

```php
add_action( 'init', function() {
    register_block_ability( 'egnitech-one-child/peptide-landing', 'tone-shift', array(
        'label'       => __( 'Scientific Tone Shift', 'egnitech-one-child' ),
        'description' => __( 'Converts marketing copy to scientific research tone.', 'egnitech-one-child' ),
        'intent'      => 'scientific_validation', // Elite: Add intent for AI discovery
    ) );
} );
```

---

## Interactivity API: Native Asset Loading (Script Modules)

In 2026, we use the **Script Modules API** to handle Interactivity API assets.

**Step 1: Register the script module in PHP**
```php
wp_register_script_module(
    'my-theme/logic',
    get_stylesheet_directory_uri() . '/patterns/my-pattern/index.js',
    array( '@wordpress/interactivity' ),
    '1.0.0'
);
```

**Step 2: Bind to the block variation**
```php
register_block_style( 'core/group', array(
    'name'                 => 'my-pattern',
    'script_module_handle' => 'my-theme/logic',
));
```

WordPress now automatically enqueues the module only when a block with the `is-style-my-pattern` class is present.

**Elite Automation: Block Hooks**
For mandatory pattern logic that should never be missed by clients, use Block Hooks to wrap the pattern in a logic-providing block automatically:

```php
add_filter( 'hooked_block_types', function( $hooked_blocks, $relative_block_type, $section, $context ) {
    if ( 'egnitech-one-child/my-pattern' === $relative_block_type && 'after' === $section ) {
        $hooked_blocks[] = 'my-theme/logic-provider-block';
    }
    return $hooked_blocks;
}, 10, 4 );
```

---

## Interactivity API (Modern JS)

The Interactivity API is the modern standard for frontend logic. It uses declarative markup instead of procedural jQuery.

```html
<div 
  data-wp-interactive="my-theme/navigation" 
  data-wp-context='{ "isOpen": false }'
>
  <button 
    data-wp-on--click="actions.toggleMenu"
    data-wp-bind--aria-expanded="context.isOpen"
  >
    Toggle Menu
  </button>
  
  <nav data-wp-bind--hidden="!context.isOpen">
    <!-- Menu items -->
  </nav>
</div>
```

*Note: Requires registering the store in `index.js` using `store()`. Elite usage avoids `event.target.style` and instead toggles state attributes or CSS classes via `data-wp-class` or `data-wp-style`.*

---

## Synced Pattern Overrides (WP 7.0+)

For sections like Heroes or CTAs used across multiple pages, **Synced Patterns with Overrides** are the 2026 gold standard. They allow you to:
1.  **Sync the Design**: Changing the layout/CSS in the pattern updates every instance.
2.  **Override Content**: Users can change the text/images on a per-page basis without breaking the sync.

**Nested Overrides**: Elite 2026 patterns support overrides inside nested structures. You can override a button's text inside a synced card that is itself part of a synced grid.

**Requirement**: In the pattern's block attributes, set `"__experimentalRole": "content"` on the blocks you want to allow overrides for.

---

## Speculation Rules API (Instant Transitions)

In 2026, elite themes feel like SPAs. Use the Speculation Rules API to pre-render links:

```php
add_action( 'wp_head', function() {
    ?>
    <script type="speculationrules">
    {
      "prerender": [
        {
          "source": "list",
          "urls": ["/shop", "/contact"]
        },
        {
          "where": { "href_matches": "/*" },
          "eagerness": "moderate"
        }
      ]
    }
    </script>
    <?php
}, 1 );

/**
 * Elite 2026: Multi-step pre-rendering.
 * Automatically pre-renders the next page in a sequence based on user hover patterns.
 */
add_action( 'wp_footer', function() {
    if ( is_page( 'multi-step-form' ) ) {
        // Logic to dynamically inject speculation rules based on current step
    }
} );
```

---

## Data Views (Client Dashboards)

Top developers provide custom Data View configurations in the Site Editor for Custom Post Types, creating a "SaaS-like" content management experience for clients.

```php
// Register custom data view for lab results
add_filter( 'block_editor_rest_api_get_items_query_params', function( $params, $post_type ) {
    if ( 'lab_result' === $post_type ) {
        // Custom logic to modify how items appear in the Site Editor Data Views
    }
    return $params;
}, 10, 2 );

/**
 * Elite 2026: dataviews.json
 * Define the default view (List, Grid, Table) and visible fields for clients.
 */
```

---

## Accessibility (A11y) Standards

A Top 1% theme is an accessible theme.

1. **Semantic Landmarks**: Use `tagName: "header"`, `"footer"`, `"main"`, `"section"` in Group blocks.
2. **Aria Labels**: Add `aria-label` to navigation blocks and interactive buttons.
3. **Alt Text**: Ensure all `core/image` blocks have `alt` attributes or are bound to dynamic alt text.
4. **Focus States**: Never disable focus outlines in CSS without providing a high-contrast alternative.

---

### Required Header

Every PHP pattern file MUST start with this header comment (PHP comment, not HTML):

```php
<?php
/**
 * Title: Human Readable Title
 * Slug: theme-text-domain/pattern-slug
 * Categories: category-slug
 * Keywords: keyword1, keyword2
 * Block Types: core/group
 * Inserter: true
 */
?>
```

- `Title` — shown in the Block Inserter
- `Slug` — must be unique; format: `{text-domain}/{slug}`
- `Categories` — must be a registered category slug
- `Inserter: false` — hides the pattern from the inserter (useful for templates-only patterns)

### Image URL Pattern (always use PHP)

```php
<img
  src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/images/hero.webp"
  alt="<?php esc_attr_e( 'Hero image', 'text-domain' ); ?>"
>
```

Never hardcode `http://` or relative paths — they break across environments and when child
theme overrides parent.

### Pattern Category Registration

Register custom categories in `functions.php` before patterns use them:

```php
function mytheme_register_pattern_categories() {
    register_block_pattern_category(
        'my-theme',
        array( 'label' => __( 'My Theme', 'my-theme' ) )
    );
}
add_action( 'init', 'mytheme_register_pattern_categories' );
```

---

## Asset Pipeline (functions.php)

### Full functions.php Template

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue parent and child stylesheets.
 */
function mytheme_child_enqueue_styles() {
    wp_enqueue_style(
        'mytheme-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme()->parent()->get( 'Version' )
    );
}
add_action( 'wp_enqueue_scripts', 'mytheme_child_enqueue_styles', 9 );

/**
 * Theme setup: pattern categories.
 */
function mytheme_child_setup() {
    register_block_pattern_category(
        'egnitech-one-child',
        array( 'label' => __( 'Egnitech One Child', 'egnitech-one-child' ) )
    );
}
add_action( 'after_setup_theme', 'mytheme_child_setup' );

/**
 * Register block styles and assets for patterns.
 */
function mytheme_child_pattern_assets() {
    // Elite 2026 Standard: Use wp_enqueue_block_style for global child resets
    // This ensures child overrides load after core block styles.
    wp_enqueue_block_style(
        'core/group',
        array(
            'handle' => 'mytheme-child-global-resets',
            'src'    => get_stylesheet_directory_uri() . '/assets/css/global-resets.css',
            'path'   => get_stylesheet_directory() . '/assets/css/global-resets.css',
        )
    );

    // 1. Register the stylesheet for a specific sub-pattern
    wp_register_style(
        'my-section-style',
        get_stylesheet_directory_uri() . '/patterns/my-section/style.css',
        array(),
        '1.0.0'
    );
    // Add absolute path to trigger native WP CSS inlining for small files
    wp_style_add_data(
        'my-section-style',
        'path',
        get_stylesheet_directory() . '/patterns/my-section/style.css'
    );

    // 2. Register the script module for the pattern
    wp_register_script_module(
        'my-section-logic',
        get_stylesheet_directory_uri() . '/patterns/my-section/index.js',
        array( '@wordpress/interactivity' ),
        '1.0.0'
    );

    // 3. Bind it to a block style variation
    register_block_style(
        'core/group',
        array(
            'name'                 => 'my-section',
            'label'                => 'My Section',
            'style_handle'         => 'my-section-style',
            'script_module_handle' => 'my-section-logic',
        )
    );
}
add_action( 'init', 'mytheme_child_pattern_assets' );
```

### Key Notes

- `get_template_directory_uri()` → **parent** theme directory
- `get_stylesheet_directory_uri()` → **child** (or current) theme directory
- Always pass the parent theme's version as the stylesheet version to bust cache on parent updates
- Priority `9` for child enqueue ensures it loads before default priority `10` hooks

---

## Site Editor Sync Behaviour

The Site Editor stores user customisations in the **database**. If a theme file changes but the
editor isn't reflecting it, the database version is taking precedence.

### How to force file-based version

1. Open the Site Editor (Appearance → Editor)
2. Navigate to the affected template or style
3. Click the three-dot menu (⋮)
4. Select **"Reset to defaults"** or **"Clear Customizations"**

This removes the database override and forces WordPress to re-read the file.

### When this matters most

- After editing a `templates/*.html` file
- After editing `theme.json` styles
- After moving a template part to a new area in `theme.json`
- After adding a new **Ability** to a pattern

### Programmatic cache busting (development)

During active development, append a unique version string to style/script handles to force
browser cache invalidation:

```php
wp_enqueue_style( 'my-style', get_stylesheet_directory_uri() . '/style.css', array(), time() );
```

Replace `time()` with a static version string before deploying to production.

---

## Child Theme Rules

1. **Never modify the parent theme directory.** All files go in the child theme.
2. **Template override**: Copy `templates/{name}.html` from the parent to the same path in the
   child and modify. WordPress always prefers the child theme's version.
3. **Part override**: Same process — copy from parent `parts/` to child `parts/`.
4. **Pattern override**: Create a pattern with the same `Slug` in the child theme to override
   the parent's pattern.
5. **theme.json merging**: Child's `theme.json` is merged with the parent's. However, arrays like `palette` or `fontFamilies` are **overwritten entirely**, not concatenated. If you want to add a single new color, you MUST copy the parent's entire palette array and append your color, otherwise the parent colors will be wiped out.

---

## Query Loop Block

The Query Loop block powers archive, category, and search templates. The critical setting:

**"Inherit query from template"** (set in the block's sidebar)

- **On (true)**: The block reads its query parameters from the current URL (category slug,
  search term, pagination). Use this for `archive.html` and `search.html`.
- **Off (false)**: The block ignores the URL and always shows a static list of recent posts.
  This BREAKS search and archive functionality — only use it for manually curated post grids.

In block markup, this setting corresponds to `"inherit": true` in the query attribute:

```html
<!-- wp:query {"queryId":1,"query":{"inherit":true,"postType":"post"}} -->
```

---

## Diagnostic Console Snippet

When a header or footer section appears missing or invisible, paste this in browser DevTools
(Console tab) to quickly determine whether the issue is DOM-level (template part not rendered)
or CSS-level (part renders but is invisible):

```javascript
(function() {
  const header = document.querySelector('header');
  const report = {
    headerExists: !!header,
    headerInnerHTML: header ? header.innerHTML.trim().substring(0, 500) : 'N/A',
    headerChildren: header ? Array.from(header.children).map(c => ({
      tag: c.tagName, classes: c.className,
      visible: c.offsetWidth > 0 && c.offsetHeight > 0
    })) : [],
    headerStyles: header ? {
      display: getComputedStyle(header).display,
      height: getComputedStyle(header).height,
    } : null
  };
  console.log(JSON.stringify(report, null, 2));
  try { copy(JSON.stringify(report, null, 2)); } catch(e) {}
})();
```

**Interpreting results:**
- `headerChildren` with `visible: false` + empty `innerHTML` → template part not rendering
  → check `Inserter: false` on the pattern / PHP-only block nesting of `wp:template-part`
- `headerChildren` with `visible: false` + non-empty `innerHTML` → CSS not loading
  → check that `register_block_style()` is firing and the block has the `is-style-...` class
- `headerChildren` with `visible: true` → working correctly
