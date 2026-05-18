# FSE Architecture Reference — WordPress Block Themes
*Default target: WordPress 7.0 (released 2026-05-20). For WP 6.9 or earlier, substitute the fallbacks in `compatibility-6.9.md`.*

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

### Template Registration API (PHP)

`register_block_template()` (introduced in 6.7) registers a template from PHP. This is useful for plugins or for themes that need templates to ship as code rather than as `.html` files inside `templates/`.

```php
add_action( 'init', function() {
    register_block_template( '{{THEME_SLUG}}//my-landing-page', array(
        'title'       => __( 'My Landing Page', '{{TEXT_DOMAIN}}' ),
        'description' => __( 'A high-performance landing page template.', '{{TEXT_DOMAIN}}' ),
        'content'     => '<!-- wp:pattern {"slug":"{{THEME_SLUG}}/my-landing-page"} /-->',
        'post_types'  => array( 'page' ),
    ) );
} );
```

The template name must be in the form `namespace//template_name` (two slashes). Supported `$args` keys are `title`, `description`, `content`, and `post_types`. See developer.wordpress.org/reference/functions/register_block_template/ for the full reference.

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
<!-- Correct: raw HTML rendered by a dynamic block registered via register_block_type() -->
<!-- wp:my-theme/legacy-section /-->

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

Map every part of the design to a Core Block first. Only fall back to a dynamic block (`register_block_type()` + `render_callback`) when no core block fits — that keeps colors, typography, and spacing under Site Editor control.

| HTML Tag | Core Block | Attributes to use |
|---|---|---|
| `<div>` (wrapper) | `core/group` | `tagName: "div"`, `layout: { "type": "constrained" }` |
| `<section>` | `core/group` | `tagName: "section"` |
| `<h1>` – `<h6>` | `core/heading` | `level: 1-6` |
| `<p>` | `core/paragraph` | |
| `<ul>` / `<ol>` | `core/list` | |
| `<img>` | `core/image` | |
| SVG icon | `core/icon` (WP 7.0) | Register theme icons against the new Icon Registration API; see the Bindings section above. |
| 2–3 columns | `core/columns` | For 4+ columns or auto-fitting layouts, use `core/group` with `layout.type = "grid"` instead. |
| Breadcrumb trail | `core/breadcrumbs` (WP 7.0) | Auto-renders the page hierarchy. Use `block_core_breadcrumbs_items` to customise the trail (e.g. inject a custom segment, hide ancestors). |
| Video background `<section>` | `core/cover` with embedded video (WP 7.0) | Cover block accepts YouTube/Vimeo URLs as background, not just locally uploaded files. |
| Gallery with full-screen view | `core/gallery` with lightbox (WP 7.0) | Set `lightbox: true`; in 7.0 the lightbox supports arrow-key navigation between images. |

### Core Blocks new in WP 7.0

- **`core/breadcrumbs`** — automatic page hierarchy. The shipped trail is filterable via `block_core_breadcrumbs_items`:

  ```php
  add_filter( 'block_core_breadcrumbs_items', function( $items, $block ) {
      // $items is an ordered array of trail segments. Modify, add, or remove entries here.
      return $items;
  }, 10, 2 );
  ```

- **`core/icon`** — server-side rendered, backed by `/wp/v2/icons`. Register theme icons against the new Icon Registration API so they appear in the inserter alongside core's curated set. Until the final 7.0 helper signature is documented in stable, fall back to a PHP icon helper (see `compatibility-6.9.md`).

- **`core/cover` enhancements** — embedded YouTube/Vimeo videos as background; focal-point control on fixed backgrounds.

- **`core/gallery` enhancements** — lightbox now navigates between images with the arrow keys.

### Native Grid layout (WP 7.0 hybrid mode)

WordPress 7.0 lets the Grid layout use `columnCount` and `minimumColumnWidth` at the same time. Set both to get a responsive grid that respects a maximum column count but reflows on narrow viewports.

```html
<!-- wp:group {
  "tagName": "section",
  "layout": {
    "type": "grid",
    "columnCount": 3,
    "minimumColumnWidth": "280px"
  }
} -->
<section class="wp-block-group">
  <!-- cards go here -->
</section>
<!-- /wp:group -->
```

On 6.9 and earlier, only one of the two could be set; the WP 7.0 hybrid mode removes that limitation.

---

## Dynamic Blocks (PHP-only) — WP 7.0

If no Core Block fits a section of the design, register a dynamic block instead of pasting raw HTML. WordPress 7.0 introduces the `autoRegister` flag, which lets you register a block entirely from PHP — no `block.json`, no JavaScript, no build step.

```php
add_action( 'init', function() {
    register_block_type( '{{THEME_SLUG}}/legacy-section', array(
        'title'       => __( 'Legacy Section', '{{TEXT_DOMAIN}}' ),
        'description' => __( 'A custom layout that does not fit core blocks.', '{{TEXT_DOMAIN}}' ),
        'category'    => 'theme',
        'supports'    => array(
            'autoRegister' => true,
            'html'         => false,
        ),
        'attributes'  => array(
            'heading' => array(
                'type'    => 'string',
                'default' => '',
            ),
        ),
        'render_callback' => function( $attributes, $content, $block ) {
            $heading = isset( $attributes['heading'] ) ? esc_html( $attributes['heading'] ) : '';
            return sprintf(
                '<section class="legacy-section"><h2>%s</h2>%s</section>',
                $heading,
                wp_kses_post( $content )
            );
        },
    ) );
} );
```

With `autoRegister => true`, WordPress generates the inserter UI from the `attributes` array (it supports `string`, `integer`, `boolean`, and `enum`) and renders the block via `ServerSideRender` in the editor. Use this for static or mostly-static dynamic markup that does not need interactive React controls.

For 6.9 and earlier, register the block the long way with a `block.json` + `render.php` pair; the `autoRegister` flag is silently ignored on older cores.

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

### Content-Only Locking

For client-safe patterns, use `contentOnly` locking. Editors can change text and images but cannot move, remove, or change the structure of blocks.

```json
"templateLock": "contentOnly"
```

Apply this to the outermost wrapper of a master pattern when you want a fixed layout with editable content.

---

## Block Bindings API (Dynamic Data)

Introduced in 6.5, the Block Bindings API connects Core Blocks to dynamic data without writing custom patterns or React. In WP 7.0, any block attribute that supports bindings also supports Pattern Overrides.

Built-in sources: `core/post-meta`, `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-author-name`, `core/site-title`, `core/site-tagline`, `core/pattern-overrides`.

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
```

### Registering a Custom Binding Source

To expose theme-specific data to the binding UI:

```php
add_action( 'init', function() {
    register_block_bindings_source( '{{THEME_SLUG}}/custom-source', array(
        'label'              => __( 'Custom Source', '{{TEXT_DOMAIN}}' ),
        'get_value_callback' => '{{THEME_SLUG}}_get_binding_value',
        'uses_context'       => array( 'postId' ),
    ) );
} );
```

See developer.wordpress.org/reference/functions/register_block_bindings_source/.

### `core/icon` block and theme icons (WP 7.0)

WP 7.0 ships a new `core/icon` block backed by a REST endpoint at `/wp/v2/icons`. The Icon block is rendered server-side; icons supplied by themes and plugins appear in the inserter alongside core's curated set. Until the final 7.0 icon registration helper lands in stable docs, register theme icons via the documented filter approach (see the developer notes at make.wordpress.org/core for the latest signature). When in doubt, fall back to the helper-function approach documented in `compatibility-6.9.md`.

---

## Abilities API (WP 7.0)

The Abilities API is stable in WordPress 7.0. Themes and plugins register named "abilities" — server-side callbacks with JSON Schema input/output contracts — that the WP AI Client and other tools can discover and invoke.

The registration sequence is: register an ability **category** first, then register the ability itself. Both calls must happen on the `wp_abilities_api_init` action.

```php
add_action( 'wp_abilities_api_init', function() {
    // 1. Register a category that groups related abilities.
    wp_register_ability_category( '{{THEME_SLUG}}/editorial', array(
        'label'       => __( 'Editorial Tools', '{{TEXT_DOMAIN}}' ),
        'description' => __( 'AI-assisted writing helpers for this theme.', '{{TEXT_DOMAIN}}' ),
    ) );

    // 2. Register the ability inside that category.
    wp_register_ability( '{{THEME_SLUG}}/tone-shift', array(
        'label'              => __( 'Tone Shift', '{{TEXT_DOMAIN}}' ),
        'description'        => __( 'Rewrites the selected copy in a chosen tone.', '{{TEXT_DOMAIN}}' ),
        'category'           => '{{THEME_SLUG}}/editorial',
        'input_schema'       => array(
            'type'       => 'object',
            'properties' => array(
                'text' => array( 'type' => 'string' ),
                'tone' => array(
                    'type' => 'string',
                    'enum' => array( 'scientific', 'casual', 'formal' ),
                ),
            ),
            'required'   => array( 'text', 'tone' ),
        ),
        'output_schema'      => array(
            'type'       => 'object',
            'properties' => array(
                'rewritten' => array( 'type' => 'string' ),
            ),
        ),
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'execute_callback'    => function( $input ) {
            // Call the WP AI Client (or any provider) here and return the result.
            return array( 'rewritten' => $input['text'] );
        },
    ) );
} );
```

The ability name must be lowercase, namespaced (`prefix/name`), and may contain only letters, digits, dashes, and forward slashes. See developer.wordpress.org/reference/functions/wp_register_ability/ for the full reference. Categories are documented at developer.wordpress.org/apis/abilities-api/.

### WP AI Client + Connectors (WP 7.0)

The WP AI Client (`WP_AI_Client_Prompt_Builder`) is a provider-agnostic PHP wrapper around the AI providers configured at **Settings → Connectors**. Out of the box, WP 7.0 ships connectors for OpenAI, Anthropic (Claude), and Google (Gemini); third-party providers can register their own.

Inside an ability's `execute_callback`, you can call the AI Client to fulfil the request:

```php
'execute_callback' => function( $input ) {
    $prompt = ( new WP_AI_Client_Prompt_Builder() )
        ->add_system_message( 'You are an editorial assistant.' )
        ->add_user_message( sprintf( 'Rewrite this text in a %s tone:\n\n%s', $input['tone'], $input['text'] ) );

    $response = $prompt->send();
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    return array( 'rewritten' => $response['text'] );
}
```

The user must enable at least one provider at Settings → Connectors for `send()` to succeed. If no connector is configured, `send()` returns a `WP_Error` — surface that to the user rather than swallowing it.

---

## Interactivity API: Native Asset Loading (Script Modules)

Use the **Script Modules API** to ship Interactivity API code so it loads only when the relevant block is on the page.

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
    if ( '{{THEME_SLUG}}/my-pattern' === $relative_block_type && 'after' === $section ) {
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

## Synced Pattern Overrides

For sections like Heroes or CTAs reused across multiple pages, use Synced Patterns with Overrides:

1. **Sync the design** — changing the layout/CSS in the pattern updates every instance.
2. **Override the content** — editors change text/images on a per-page basis without breaking the sync.

In WP 7.0, any block attribute that supports Block Bindings also supports Pattern Overrides, including custom blocks.

**How to mark a block as overridable.** Inside the pattern, set `metadata.bindings` to point at the `core/pattern-overrides` source and give the binding a stable `metadata.name`:

```html
<!-- wp:heading {
  "level": 1,
  "metadata": {
    "name": "hero-headline",
    "bindings": {
      "content": { "source": "core/pattern-overrides" }
    }
  }
} -->
<h1 class="wp-block-heading">Default headline</h1>
<!-- /wp:heading -->
```

**Editor UI.** In the editor, editors select the block, open the block sidebar, expand **Advanced**, and click **Enable overrides**. Once enabled, instances of the synced pattern can override the marked attributes while keeping the rest of the design in sync.

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
 * Optional: inject additional speculation rules based on the current page.
 */
add_action( 'wp_footer', function() {
    if ( is_page( 'multi-step-form' ) ) {
        // Inject extra speculation rules for the next step in the flow.
    }
} );
```

---

## Data Views

For custom post types, you can customise the Site Editor's Data View by filtering the REST query parameters. This gives editors a tailored grid/list/table view of their content.

```php
add_filter( 'block_editor_rest_api_get_items_query_params', function( $params, $post_type ) {
    if ( 'lab_result' === $post_type ) {
        // Adjust how items are fetched/displayed in the Site Editor Data View.
    }
    return $params;
}, 10, 2 );
```

---

## Accessibility (A11y)

1. **Semantic landmarks**: use `tagName: "header"`, `"footer"`, `"main"`, `"section"` on Group blocks.
2. **ARIA labels**: add `aria-label` to navigation blocks and any unlabelled interactive buttons.
3. **Alt text**: every `core/image` block needs an `alt` attribute, or a binding that supplies dynamic alt text.
4. **Focus states**: never disable focus outlines without providing a visible, high-contrast alternative.

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
        '{{THEME_SLUG}}',
        array( 'label' => __( '{{THEME_NAME}}', '{{TEXT_DOMAIN}}' ) )
    );
}
add_action( 'after_setup_theme', 'mytheme_child_setup' );

/**
 * Register block styles and assets for patterns.
 */
function mytheme_child_pattern_assets() {
    // Use wp_enqueue_block_style for global child resets so they load after core block styles.
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
  → check `Inserter: false` on the backing pattern, and confirm `wp:template-part` is not nested inside a dynamic block's render output
- `headerChildren` with `visible: false` + non-empty `innerHTML` → CSS not loading
  → check that `register_block_style()` is firing and the block has the `is-style-...` class
- `headerChildren` with `visible: true` → working correctly
