# FSE Architecture Reference — WordPress Block Themes
*Target: WordPress 7.0 and later, verified against 7.0.8 and 7.1.4. Features that need 7.1 are in `references/core-7.1-apis.md`; never emit them for a 7.0 site.*

## Table of Contents
1. Theme Initialization Requirements
2. Directory Structure
3. Template Hierarchy & Fallback Logic
4. Block Markup Rules
5. Native-First Conversion & Block Mapping (decision algorithm, new core blocks, navigation overlays, grid)
6. Dynamic Blocks (PHP-only)
7. Block Locking (Client-Proofing) and Content-Only Editing
8. Block Bindings API; `core/icon` and theme icons
9. Abilities API and WP AI Client (pointers)
10. Script Modules: loading Interactivity code; Block Hooks
11. Interactivity API (Modern JS)
12. Synced Pattern Overrides
13. Viewport Block Visibility
14. Font Library; Iframed Editor
15. Speculative Loading (pointer)
16. Accessibility
17. WordPress 7.0 Core Environment
18. PHP Requirements (version step, rules, 8.4 features); Pattern File Header; Image URL Pattern
19. Asset Pipeline (functions.php) — the pattern-asset registry and loader
20. Site Editor Sync Behaviour
21. Child Theme Rules
22. Query Loop Block
23. Diagnostic Console Snippet

---

## Theme Initialization Requirements

WordPress recognises a block theme by `templates/index.html`. This skill always ships all three:

| File | Purpose |
|---|---|
| `style.css` | Theme metadata header: Theme Name, Version, Text Domain, **`Requires at least`** (`7.0`, or `7.1` when the theme uses 7.1 features) and **`Requires PHP`** (the PHP floor). WordPress enforces both on activation. |
| `theme.json` | Global settings and styles engine |
| `templates/index.html` | Fallback template — its presence is what makes the theme a block theme |

`functions.php` is optional in a pure block theme but almost always needed for enqueueing custom
assets, registering pattern categories, and adding editor styles.

---

## Directory Structure

```
theme-name/
├── style.css                     ← Theme metadata header
├── theme.json                    ← Global settings & styles
├── functions.php                 ← Pattern category + require_once for the inc/ files it loads
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
├── inc/                          ← PHP that functions.php loads (listed below)
│
└── assets/
    ├── fonts/
    ├── images/
    ├── js/                       ← Optional: scripts that belong to no pattern
    └── css/
```

For **modular templates** (custom landing pages), co-locate each sub-pattern's assets in a folder named after it:

```
templates/
  my-landing-page.html            ← Template entry point
patterns/
  my-landing-page.php             ← Master pattern (assembler)
  my-section.php                  ← Sub-pattern
  my-section/
    style.css                     ← Scoped CSS for the sub-pattern
    index.js                      ← Optional non-reactive JS (animations, observers)
    view.js                       ← Optional Interactivity API store for core blocks in this pattern
inc/
  pattern-assets.php              ← Registry + loader for the folders above
  interactivity.php               ← Optional: adds data-wp-* directives to core blocks at render
  woocommerce.php                 ← Optional: registers the WooCommerce checkout script (references/woocommerce.md → §6.4)
  class-my-theme-checkout-integration.php ← Optional: required only by woocommerce.php
  blocks/
    my-widget.php                 ← Optional PHP-only dynamic block
    my-widget/view.js             ← Its own Interactivity API store
```

---

## Template Hierarchy & Fallback Logic

WordPress picks a template by query type, then walks that type's chain (first match wins):

```
Static page:       custom template (if assigned) → page-{slug}.html → page-{ID}.html → page.html → singular.html → index.html
Single post:       custom template (if assigned) → single-{post-type}-{slug}.html → single-{post-type}.html → single.html → singular.html → index.html
Site front page:   front-page.html → then the page chain (static front page) or home.html (latest posts)
Blog posts index:  home.html → index.html
Category archive:  category-{slug}.html → category-{ID}.html → category.html → archive.html → index.html
Search results:    search.html → index.html
```

**Custom templates** (listed under `customTemplates` in `theme.json`) appear in the editor's template picker and bypass the hierarchy when explicitly assigned.

### Template Registration API (PHP) — PLUGINS ONLY

`register_block_template()` (since 6.7) registers a template from PHP. **This is for plugins only.** In a block theme, templates are files in `templates/`, and custom ones are listed under `customTemplates` in `theme.json`. Never register the same template both ways.

The name must be `namespace//template_name` — two slashes, lowercase — e.g. `{{THEME_SLUG}}//template_name`. Anything else triggers `_doing_it_wrong()` and returns a `WP_Error`; the template is not registered. `$args` keys: `title`, `description`, `content`, `post_types`, `plugin`.


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
| `<h1>` – `<h6>` | `core/heading` | `level: 1-6`. Editors can always change the level via the block toolbar. WP 7.0 also registers H1–H6 as named inserter-level variations, but the block name in markup is always `core/heading`. |
| `<p>` | `core/paragraph` | |
| `<ul>` / `<ol>` | `core/list` | |
| `<img>` | `core/image` | |
| SVG icon | `core/icon` (WP 7.0) | `{"icon":"core/{name}"}` with a name from the built-in set. Custom icons: see *`core/icon` and theme icons* below. |
| 2–3 columns | `core/columns` | For 4+ columns or auto-fitting layouts, use `core/group` with `layout.type = "grid"` instead. |
| Breadcrumb trail | `core/breadcrumbs` (WP 7.0) | Auto-renders the page hierarchy. Use `block_core_breadcrumbs_items` to customise the trail (e.g. inject a custom segment, hide ancestors). |
| Video background `<section>` | `core/cover` with embedded video (WP 7.0) | Cover block accepts YouTube/Vimeo URLs as background, not just locally uploaded files. |
| Gallery with full-screen view | `core/gallery` (WP 7.0 lightbox) | `"linkTo": "lightbox"` on the gallery and `"lightbox": {"enabled": true}` on each inner `core/image`. In 7.0 the lightbox moves between the gallery's images. |
| Mobile nav close button | `core/navigation-overlay-close` (WP 7.0) | Inside the navigation-overlay **template part** — see *Custom Navigation Overlays*. Not a child of `core/navigation`. |

### Native-First Decision Algorithm

When mapping a design element to a block, walk this tree in order. Stop at the first match. Only fall back to `register_block_type()` when no branch above applies.

```
Is it text content?
  → Heading (any level)?          → core/heading  (level: 1–6; toolbar always allows level switching)
  → Paragraph?                    → core/paragraph (add textIndent / textColumns if needed)
  → List?                         → core/list + core/list-item

Is it a layout wrapper?
  → 2–3 equal columns?            → core/columns + core/column
  → 4+ columns OR auto-fit grid?  → core/group  layout.type = "grid"
  → Full-bleed section (bg image/video)?  → core/cover
  → Any other div/section?        → core/group  (tagName: "section"/"div"/"article" etc.)

Is it media?
  → Single image?                 → core/image
  → Gallery?                      → core/gallery
  → Video embed (YT/Vimeo)?       → core/embed
  → Inline SVG icon?              → core/icon with a core/{name} icon; custom icons: see "core/icon and theme icons"

Is it navigation?
  → Site menu?                    → core/navigation
  → Mobile overlay close button?  → core/navigation-overlay-close, inside the overlay template part (WP 7.0)
  → Breadcrumbs?                  → core/breadcrumbs (WP 7.0)

Is it interactive / dynamic?
  → Reads post or term data (meta, date, link)?   → Block Bindings (core/post-meta, core/post-data, core/term-data)
  → Needs frontend reactivity (state, events)?
      on a core block already in the pattern?   → directives added at render with WP_HTML_Tag_Processor
                                                   + store in patterns/{slug}/view.js (route A, preferred)
      on markup core blocks cannot express?     → PHP-only dynamic block + its own store (route B)
  → Server-rendered markup core cannot express?   → PHP-only dynamic block: register_block_type() with
                                                     'supports' => array( 'autoRegister' => true ) + render_callback
  → Needs editor controls beyond the auto-generated ones?  → block.json block with its own editor script
```

**NEVER use `<!-- wp:html -->`.** Its content cannot be styled or edited through the Site Editor. A PHP-only block with `autoRegister` is the fallback for markup core blocks cannot express.

### Core Blocks new in WP 7.0

- **`core/breadcrumbs`** — automatic page hierarchy. Two filters shape the trail. `block_core_breadcrumbs_items` receives **one argument**, the items array:

  ```php
  /**
   * Adjusts the breadcrumb trail.
   */
  function {{THEME_PREFIX}}_breadcrumb_items( array $items ): array {
  	// Each item: [ 'label' => string, 'url' => string (optional), 'allow_html' => bool (optional) ].
  	// allow_html true → label passes through wp_kses_post(); otherwise esc_html().
  	return $items;
  }
  add_filter( 'block_core_breadcrumbs_items', '{{THEME_PREFIX}}_breadcrumb_items' );
  ```

  `block_core_breadcrumbs_post_type_settings` receives `( array $settings, string $post_type, int $post_id )`; return `taxonomy` and/or `term` slugs to choose which taxonomy and term appear when a post has several.

- **`core/icon`** — renders a named SVG from core's icon registry. See *`core/icon` and theme icons* below for which names exist on which version.

- **`core/cover` enhancements** — embedded YouTube/Vimeo videos as background (`"backgroundType": "embed-video"`).

- **`core/gallery` enhancements** — lightbox across the gallery's images: `"linkTo": "lightbox"` on the gallery, `"lightbox": {"enabled": true}` on each inner image.

### Custom Navigation Overlays

A navigation overlay is a template part in the `navigation-overlay` area (WP 7.0). Setup:

1. **Register the part** — in `theme.json` under `templateParts`: `{"area": "navigation-overlay", "name": "mobile-menu", "title": "Mobile Menu"}`.
2. **Create `parts/mobile-menu.html`** — and put the close button in it. Without `core/navigation-overlay-close`, WordPress inserts a fallback close button that will not match the design.

   ```html
   <!-- wp:group {"layout":{"type":"flex","orientation":"vertical"}} -->
   <div class="wp-block-group">
       <!-- wp:navigation-overlay-close /-->
       <!-- wp:navigation {"layout":{"type":"flex","orientation":"vertical"}} /-->
   </div>
   <!-- /wp:group -->
   ```

3. **Point the Navigation block at it** — by slug only, no theme prefix: `<!-- wp:navigation {"overlayMenu":"mobile","overlay":"mobile-menu"} /-->`.
4. **Optional: offer overlay designs as patterns** — a pattern registered with `Block Types: core/template-part/navigation-overlay` appears as a choice when an editor creates or replaces an overlay. It does not make an overlay active; step 3 does.

`core/navigation-overlay-close` is not an allowed child of `core/navigation`. It belongs in the overlay part only.

> **Limitations (as documented for 7.0):** overlays are always full-screen, and an overlay template part belongs to the active theme — it is not carried over on a theme switch.

---

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

---

## Dynamic Blocks (PHP-only) — WP 7.0

If no Core Block fits a section of the design, register a dynamic block instead of pasting raw HTML. WordPress 7.0 introduces the `autoRegister` flag, which lets you register a block entirely from PHP — no `block.json`, no JavaScript, no build step.

```php
/**
 * Registers the Legacy Section block.
 */
function {{THEME_PREFIX}}_register_legacy_section_block(): void {
	register_block_type(
		'{{THEME_SLUG}}/legacy-section',
		array(
			'title'           => __( 'Legacy Section', '{{TEXT_DOMAIN}}' ),
			'description'     => __( 'A custom layout that does not fit core blocks.', '{{TEXT_DOMAIN}}' ),
			'category'        => 'theme',
			'supports'        => array(
				'autoRegister' => true,
				'html'         => false,
			),
			'attributes'      => array(
				'heading' => array(
					'type'    => 'string',
					'default' => '',
					'role'    => 'content',
				),
			),
			'render_callback' => static function ( array $attributes ): string {
				return sprintf(
					'<section %1$s><h2>%2$s</h2></section>',
					get_block_wrapper_attributes(),
					esc_html( $attributes['heading'] ?? '' )
				);
			},
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_legacy_section_block' );
```

With `autoRegister => true`, WordPress registers the block in the editor, generates inspector controls from the `attributes` array, and previews it with `ServerSideRender`. Use this for server-rendered markup that does not need custom React controls.

**Requirements and constraints:**
- `render_callback` is **mandatory** — `autoRegister` blocks have no JS editor component; the callback is the only render path.
- Inspector controls are generated for attributes of type `string`, `number`, `integer` and `boolean`. Other types, and attributes with `"role": "local"`, get no control.
- **No sourced attributes.** An attribute with a `source` key gets no control, and a PHP-only block has no saved HTML to source from. Keep every value in the block's comment attributes.
- **Frontend JavaScript:** bind an Interactivity API script module with `'view_script_module_ids'` and add `'interactivity' => true` to `supports`. Worked example: `references/html-conversion.md → Interactivity API (reactive logic)`.

`autoRegister` exists since WP 7.0. Do not add fallback code for older versions.

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

### Content-Only Editing (WP 7.0)

WP 7.0 opens two kinds of content in content-only mode by default — editors change text and media, not structure:

| Context | Default in 7.0 | How an editor unlocks the structure |
|---|---|---|
| Unsynced pattern in a page or template (its outermost block carries `metadata.patternName`) | Content-only | **Edit pattern**, or double-click the pattern |
| Template part shown inside a template or page | Content-only | **Edit original** — opens the part in an isolated editor |
| Synced pattern (`core/block`) | Content-only; only overridable attributes are editable in place | **Edit original** |

**What to write in pattern files: nothing.** Do not add `"templateLock": "contentOnly"` to patterns or to template-part backing patterns. The default already applies, and an explicit `templateLock` keeps applying inside the isolated editor — it would stop the site owner restructuring their own header.

Add `"templateLock": "contentOnly"` to a block only when its inner structure must stay locked in every context.

**Opt-out.** Exactly one exists, and it is site-wide — the `disableContentOnlyForUnsyncedPatterns` editor setting:

```php
/**
 * Turns off content-only editing for unsynced patterns, site-wide.
 */
function {{THEME_PREFIX}}_disable_pattern_content_only( array $settings ): array {
	$settings['disableContentOnlyForUnsyncedPatterns'] = true;
	return $settings;
}
add_filter( 'block_editor_settings_all', '{{THEME_PREFIX}}_disable_pattern_content_only' );
```

It affects unsynced patterns only; template parts and synced patterns stay content-only. There is **no per-pattern opt-out** — never emit an `__experimentalSettings` attribute for this, it does nothing. Use the filter only when the client has asked for unrestricted pattern editing across the whole site.

**Content-only does not replace `lock`.** An editor who chooses Edit pattern can move and delete blocks again. `"lock": {"move": true, "remove": true}` on the outermost wrapper is what protects the section itself.

### Block Author Requirements for contentOnly Compatibility (WP 7.0)

**This applies to every custom block you register.** When a custom block is nested inside a `contentOnly` pattern or template part, WordPress uses `"role": "content"` declarations on the block's attributes to decide which attributes (and therefore which blocks) are visible to editors in List View.

Without these declarations, the block is **hidden from List View and non-selectable** in content-only containers — a silent breakage with no console error.

**Required on every editable attribute** — in `block.json`, or in the `attributes` array of a PHP-registered block (`'role' => 'content'`):

```json
{
  "attributes": {
    "heading":     { "type": "string", "role": "content" },
    "description": { "type": "string", "role": "content" },
    "url":         { "type": "string", "role": "content" }
  }
}
```

**Alternative (when no suitable attribute exists):** Add `"contentRole": true` to the block's `supports` object. This makes the block itself visible in List View without requiring a specific content attribute, but `"role": "content"` on attributes is preferred because it exposes fine-grained overridability:

```json
{
  "supports": {
    "contentRole": true
  }
}
```

**`"listView": true` block support (WP 7.0):** For container blocks (blocks with `InnerBlocks`), declaring `"listView": true` in `supports` adds a dedicated **List View tab** to the block inspector, allowing editors to rearrange and insert inner blocks from the sidebar. Recommended for any block that wraps multiple children:

```json
{
  "supports": {
    "listView": true
  }
}
```

**Blocks to audit when working inside contentOnly patterns:** `core/buttons`, `core/list`, `core/social-links`, `core/navigation`. These core blocks already declare content roles — verify your custom wrappers that contain them do the same.

---

## Block Bindings API (Dynamic Data)

Introduced in 6.5, the Block Bindings API connects Core Blocks to dynamic data without writing custom patterns or React. In WP 7.0, any block attribute that supports bindings also supports Pattern Overrides.

Built-in sources — there are four, and no others:

| Source | `args` |
|---|---|
| `core/post-meta` | `key`: the meta key (must be registered with `show_in_rest`) |
| `core/post-data` (since 6.9) | `field`: `date`, `modified` or `link` |
| `core/term-data` (since 6.9) | `field`: `id`, `name`, `link`, `slug`, `description`, `parent`, `count` |
| `core/pattern-overrides` | none — see *Synced Pattern Overrides* |

There is no `core/post-title`, `core/site-title` or similar source. For those values use the dedicated blocks (`core/post-title`, `core/site-title`) or register a custom source.

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
/**
 * Registers the theme's custom Block Bindings source.
 */
function {{THEME_PREFIX}}_register_binding_sources(): void {
	register_block_bindings_source(
		'{{THEME_SLUG}}/custom-source',
		array(
			'label'              => __( 'Custom Source', '{{TEXT_DOMAIN}}' ),
			'get_value_callback' => '{{THEME_PREFIX}}_get_binding_value',
			'uses_context'       => array( 'postId' ),
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_binding_sources' );

/**
 * Returns the value for a bound attribute. Core passes the binding's args, the block and the attribute name.
 */
function {{THEME_PREFIX}}_get_binding_value( array $source_args, WP_Block $block_instance, string $attribute_name ): ?string {
	$post_id = $block_instance->context['postId'] ?? 0;

	return $post_id ? get_the_title( $post_id ) : null;
}
```

See developer.wordpress.org/reference/functions/register_block_bindings_source/.

### `core/icon` and theme icons

`core/icon` (WP 7.0) renders an SVG looked up by name in core's icon registry. Which names exist depends on the WordPress version:

| Version | Names that render | Custom theme icons |
|---|---|---|
| 7.0 | Only the built-in set, written `core/{name}` — e.g. `core/info`, `core/caution`, `core/error`, `core/check`, `core/star-filled`, `core/star-empty`, `core/arrow-right`, `core/menu`, `core/home`, `core/cart` | Not possible. Print the SVG from a dynamic block's `render_callback`, or use an `<img>`. |
| 7.1+ | The built-in set plus anything registered | `wp_register_icon_collection()` + `wp_register_icon()` — `references/core-7.1-apis.md → SVG Icon API` |

```html
<!-- wp:icon {"icon":"core/caution"} /-->
```

A name the registry does not know renders nothing and raises no error. So: never guess a `core/` name (there is no `core/warning`, `core/star` or `core/close`), and never write `{"icon":"{{THEME_SLUG}}/…"}` for a site on 7.0.

---

## Abilities API and WP AI Client

Not covered here — each has one canonical file. Load it before writing any ability or AI code:

- **Abilities** (PHP registration, REST exposure, JavaScript client): `references/abilities-api.md`
- **WP AI Client and Connectors** (prompt builder, feature detection, credentials, letting a model call abilities): `references/ai-client.md`

---

## Script Modules: loading Interactivity code

Interactivity API code ships as a **script module** and needs `data-wp-*` directives in the markup. Directives cannot be typed into a core block's markup in a pattern file — the editor validates a core block's saved HTML and flags the extra attributes. There are two supported ways to get them onto the page; pick by where the markup comes from.

| The interactive element is… | Route | Store lives in | Loaded by |
|---|---|---|---|
| A core block the pattern already uses (a button, a group, a list) | **A.** Add the directives at render time with `WP_HTML_Tag_Processor` | `patterns/{slug}/view.js` | `'module' => true` in the pattern-asset registry |
| Markup no core block can express | **B.** A PHP-only dynamic block prints the markup and its directives | `inc/blocks/{block}/view.js` | `view_script_module_ids` on `register_block_type()` |

Prefer route A: the section stays built from core blocks, so it remains editable in the Site Editor.

`register_block_style()` has **no** script key — a module cannot be attached to a block style variation.

### Route A — directives on a core block

The pattern marks the element with a class. Use a real `<button>` for anything clickable:

```html
<!-- wp:button {"tagName":"button","className":"js-favorite-toggle"} -->
<div class="wp-block-button js-favorite-toggle"><button type="button" class="wp-block-button__link wp-element-button">Save for later</button></div>
<!-- /wp:button -->
```

`inc/interactivity.php` adds the behaviour when that block renders. Load it from `functions.php` the same way as the loader — `require_once __DIR__ . '/inc/interactivity.php';`:

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets core/button have its directives processed on the server.
 */
function {{THEME_PREFIX}}_button_interactivity_support( array $settings ): array {
	if ( 'core/button' === $settings['name'] ) {
		$settings['supports']['interactivity'] = true;
	}

	return $settings;
}
add_filter( 'block_type_metadata_settings', '{{THEME_PREFIX}}_button_interactivity_support' );

/**
 * Adds Interactivity API directives to buttons marked js-favorite-toggle.
 */
function {{THEME_PREFIX}}_favorite_toggle_directives( string $block_content ): string {
	$processor = new WP_HTML_Tag_Processor( $block_content );

	if (
		! $processor->next_tag( array( 'class_name' => 'js-favorite-toggle' ) )
		|| ! $processor->next_tag( 'button' )
	) {
		return $block_content;
	}

	$processor->set_attribute( 'data-wp-interactive', '{{THEME_SLUG}}/favorite' );
	$processor->set_attribute( 'data-wp-on--click', 'actions.toggle' );
	$processor->set_attribute( 'data-wp-bind--aria-pressed', 'state.isSaved' );

	wp_interactivity_state( '{{THEME_SLUG}}/favorite', array( 'isSaved' => false ) );

	return $processor->get_updated_html();
}
add_filter( 'render_block_core/button', '{{THEME_PREFIX}}_favorite_toggle_directives' );
```

`patterns/{slug}/view.js` holds the store. The namespace must match `data-wp-interactive` exactly:

```js
import { store } from '@wordpress/interactivity';

const { state } = store( '{{THEME_SLUG}}/favorite', {
	actions: {
		toggle() {
			state.isSaved = ! state.isSaved;
		},
	},
} );
```

Set `'module' => true` on that pattern's registry entry and the loader enqueues `view.js` only on pages where the pattern renders. `state` is shared by every element using the namespace; when each instance needs its own value, add a `data-wp-context` attribute the same way and read it with `getContext()`.

### Route B — a PHP-only dynamic block

**Step 1: Register the module**

```php
wp_register_script_module(
	'{{THEME_SLUG}}/favorite-toggle',
	get_theme_file_uri( '/inc/blocks/favorite-toggle/view.js' ),
	array( '@wordpress/interactivity' ),
	wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' )
);
```

**Step 2: Bind it to the block type**

```php
register_block_type(
	'{{THEME_SLUG}}/favorite-toggle',
	array(
		// …title, category, render_callback…
		'supports'               => array(
			'autoRegister'  => true,
			'interactivity' => true,
		),
		'view_script_module_ids' => array( '{{THEME_SLUG}}/favorite-toggle' ),
	)
);
```

Core enqueues the module whenever the block renders. Complete block and store: `references/html-conversion.md → Interactivity API (reactive logic)`. PHP-only blocks are meant for simple server-rendered blocks; when the block needs a rich editing experience, build it with `block.json` and an editor script instead.

### Translations for a module (WP 7.0)

Script modules have their own i18n path, separate from `wp_set_script_translations()`. Call `wp_set_script_module_translations( string $id, string $domain = 'default', string $path = '' ): bool` right after registering the module; in JS the strings resolve through the usual `@wordpress/i18n` import. `load_script_module_textdomain()` (also 7.0) returns the raw translation data and is rarely needed directly.

```php
wp_set_script_module_translations( '{{THEME_SLUG}}/favorite-toggle', '{{TEXT_DOMAIN}}' );
```

### Block Hooks

Block Hooks insert a block next to every instance of an **anchor block type** — before it, after it, or as its first or last child — without editing the templates that contain the anchor. The anchor is always a block type such as `core/post-content` or `core/navigation` — never a pattern slug.

```php
/**
 * Inserts the newsletter block after every Post Content block.
 */
function {{THEME_PREFIX}}_hook_newsletter_signup( array $hooked_block_types, string $relative_position, string $anchor_block_type, mixed $context ): array {
	if ( 'core/post-content' === $anchor_block_type && 'after' === $relative_position ) {
		$hooked_block_types[] = '{{THEME_SLUG}}/newsletter-signup';
	}
	return $hooked_block_types;
}
add_filter( 'hooked_block_types', '{{THEME_PREFIX}}_hook_newsletter_signup', 10, 4 );
```

Argument order matters: position (`before`, `after`, `first_child`, `last_child`) is the **second** argument, the anchor block type the **third**. The hooked block must be a registered block type.

**Post types (WP 7.0).** On the frontend, hooked blocks are applied to any post content. In the editor they are applied through the REST API for `post`, `page`, `wp_block` and `wp_navigation` only. Add a custom post type with `rest_block_hooks_post_types`:

```php
/**
 * Applies Block Hooks to the portfolio post type in the editor.
 */
function {{THEME_PREFIX}}_block_hooks_post_types( array $post_types ): array {
	$post_types[] = 'portfolio';
	return $post_types;
}
add_filter( 'rest_block_hooks_post_types', '{{THEME_PREFIX}}_block_hooks_post_types' );
```

The functions that do the insertion (`insert_hooked_blocks_into_rest_response()`, `apply_block_hooks_to_content_from_post_object()`) are private — use the filters, never call them.

**Resolving pattern references in a block tree.** `resolve_pattern_blocks( $blocks )` (since 6.6) walks a parsed block tree and replaces `core/pattern` references with the blocks they stand for. It matters only for code that consumes `parse_blocks()` output; normal rendering resolves patterns by itself.

---

## Interactivity API (Modern JS)

The Interactivity API is the modern standard for frontend logic. It uses declarative markup instead of procedural jQuery. The markup below is the **rendered result** — the directives get there through route A or B above, never by typing them into a core block's markup in a pattern file.

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

*Note: the store is registered in `view.js` with `store()`, and context is read there with `getContext()`. Toggle state through `data-wp-class` / `data-wp-bind`, not by writing `event.target.style`.*

### Reactive subscriptions: `watch()` (WP 7.0) and `data-wp-watch`

The `data-wp-watch` directive has existed since 6.5. The `watch()` function is new in 7.0. Use either when a block must react to state changes without a direct user event — e.g. syncing a cart count badge when items are added from another block.

**Programmatic (`view.js`):**

```js
import { store, watch } from '@wordpress/interactivity';

const { state } = store( 'my-theme/cart', {
    state: { count: 0 },
    callbacks: {
        onCountChange() {
            // Runs once on init and again whenever state.count changes.
            document.title = `(${state.count}) My Shop`;
        },
    },
} );

// Returns a teardown function — call it to remove the subscription.
const unwatch = watch( () => {
    console.log( 'Cart count:', state.count );
} );
```

**Declarative (markup):**

```html
<div
  data-wp-interactive="my-theme/cart"
  data-wp-watch="callbacks.onCountChange"
>
  <!-- Callback fires whenever any reactive state it reads changes. -->
</div>
```

Decision: use `data-wp-watch` when the side effect is tied to a specific DOM element. Use programmatic `watch()` when the side effect is global (e.g. updating `document.title` or writing a cookie).

#### `state.url` is server-populated (WP 7.0)

In WP 7.0 the `core/router` store exposes a `state.url` field that is **populated server-side on initial page load** — earlier versions only set it after the first client-side navigation. Combined with `watch()`, this gives a single, reliable hook for SPA-style analytics and side effects that previously needed a `DOMContentLoaded` + history-API combo:

```js
import { store, watch } from '@wordpress/interactivity';
const { state } = store( 'core/router' );

watch( () => {
    sendAnalyticsPageView( state.url );
} );
```

The callback fires once on initial load (because `state.url` is already populated) and again on every client-side route change. Do not read `window.location.href` for this purpose — it bypasses the router and misses post-route updates.

---

## Synced Pattern Overrides

**A theme cannot ship a synced pattern.** Every file in `patterns/` registers an *unsynced* pattern; there is no header that makes one synced. A synced pattern is a `wp_block` post that a user creates in the Site Editor (or converts from an inserted pattern), and Pattern Overrides only work inside one. Never promise override behaviour from a theme pattern file — describe the editor steps instead.

For sections like Heroes or CTAs reused across multiple pages, a site owner can use Synced Patterns with Overrides:

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

### Pattern Overrides for Custom Blocks (WP 7.0)

Before 7.0, Pattern Overrides were limited to a fixed list of core blocks. In 7.0 any block attribute that supports Block Bindings supports overrides, and a custom block opts its attributes in with the `block_bindings_supported_attributes` filter (the filter itself exists since 6.9).

```php
/**
 * Lets the Product Card block's heading and description be overridden in synced patterns.
 */
function {{THEME_PREFIX}}_product_card_override_attributes( array $supported, string $block_name ): array {
	if ( '{{THEME_SLUG}}/product-card' === $block_name ) {
		$supported[] = 'heading';
		$supported[] = 'description';
	}

	return $supported;
}
add_filter( 'block_bindings_supported_attributes', '{{THEME_PREFIX}}_product_card_override_attributes', 10, 2 );
```

After this filter is registered, a synced pattern containing a `{{THEME_SLUG}}/product-card` block will show the "Enable overrides" toggle for the `heading` and `description` attributes in the block sidebar. The custom block must declare these attributes in its `block.json` or `register_block_type()` `attributes` array.

---

## Viewport Block Visibility (WP 7.0)

Hide a block on chosen device sizes with the block's own `metadata.blockVisibility` attribute. This is the WordPress-native mechanism — prefer it to hand-written `display: none` breakpoints when a whole block should disappear on a device.

**Shape.** `viewport` is an **object** keyed by `mobile`, `tablet`, `desktop`. The block is hidden wherever the value is `false`. Omitted keys mean visible.

```html
<!-- wp:group {"metadata":{"blockVisibility":{"viewport":{"mobile":false}}}} -->
<div class="wp-block-group">
  <!-- Hidden on mobile by an injected CSS class. Still present in the DOM. -->
</div>
<!-- /wp:group -->
```

| Goal | `metadata.blockVisibility` |
|---|---|
| Show on all devices | Omit it (default) |
| Hide on mobile only | `{"viewport": {"mobile": false}}` |
| Desktop only | `{"viewport": {"mobile": false, "tablet": false}}` |
| Mobile only | `{"viewport": {"tablet": false, "desktop": false}}` |
| Never render the block at all | `false` (the whole attribute, since 6.9) |

An array such as `["tablet", "desktop"]` is **not** a valid value — it is ignored and the block shows everywhere.

**Breakpoints.** Mobile is up to 480px, tablet up to 782px, desktop above. They are fixed on 7.0; on 7.1+ `settings.viewport` in `theme.json` changes them (`references/core-7.1-apis.md`).

**It is CSS, not removal.** Viewport hiding adds a class with `display: none` inside a media query. The markup is in the page source on every device, so scripts and crawlers still see it. Never use it for access control. Only `"blockVisibility": false` keeps a block out of the output.

**Nothing to enable.** There is no `theme.json` setting for this on 7.0 — `settings.blockVisibility.viewport` is not a real key. A block type opts *out* with `"visibility": false` in its `supports`.

**Reading it in PHP.** The attribute is either the boolean `false` or an array with a `viewport` key — handle both:

```php
$visibility = $block['attrs']['metadata']['blockVisibility'] ?? null;

if ( false === $visibility ) {
	// Not rendered anywhere.
} elseif ( is_array( $visibility ) && isset( $visibility['viewport'] ) ) {
	$hidden_on = array_keys( $visibility['viewport'], false, true ); // e.g. array( 'mobile' )
}
```

---

## Font Library (WP 7.0)

Since 7.0 the Font Library has its own screen for **every** theme type — block, hybrid and classic — at **Appearance → Fonts**. Fonts a user installs there are stored in `wp-content/uploads/fonts` and survive theme switches.

- Fonts declared in `theme.json` `fontFamilies` (files in the theme, version-controlled) and fonts installed through the Font Library both appear in the editor's typography controls.
- `wp_register_font_collection()` (since 6.5) registers a collection of installable fonts; it does not install or activate a font.

### Agent rule

- User says "add a font" to the theme → `theme.json` `fontFamilies` with the files under `assets/fonts/`.
- User says "install a font" or "the font picker is missing X" → direct them to Appearance → Fonts.
- Do NOT enqueue a font stylesheet for a family that `theme.json` or the Font Library already provides — it loads the font twice.

---

## Iframed Editor (Block API v3)

On **7.0** the post editor canvas is an iframe when every block in the post uses `"apiVersion": 3` or higher. If a block with a lower API version is present, the editor falls back to a non-iframed canvas for that post. On **7.1+** the post editor is always iframed. The Site Editor is always iframed.

Consequences for theme and block code:
- Editor-side JavaScript must not reach the canvas through the global `document` / `window` — those belong to the admin page, not the canvas. Take the document from an element inside the canvas (`element.ownerDocument`).
- A block that ships a `block.json` MUST declare `"apiVersion": 3`. On 7.0 a single older block drops the whole post out of the iframe.
- PHP-only blocks (`autoRegister`) have no `block.json`; do not generate one for them.
- Editor-side scripts (inspector controls, sidebar panels) go on `enqueue_block_editor_assets`. Styles the canvas must show go on `enqueue_block_assets`.
- Frontend scripts are unaffected: a pattern's `index.js` / `view.js` and a block's `view.js` are never loaded into the editor by this skill's pipeline.

---

## Speculative Loading (prefetch / prerender)

Core prints speculation rules itself (since 6.8). **Never** output a `<script type="speculationrules">` tag from a theme. To change mode, eagerness or excluded paths, use the filters in `references/core-7.0-apis.md → §5`.

---

## Accessibility (A11y)

1. **Semantic landmarks**: use `tagName: "header"`, `"footer"`, `"main"`, `"section"` on Group blocks.
2. **ARIA labels**: add `aria-label` to navigation blocks and any unlabelled interactive buttons.
3. **Alt text**: every `core/image` block needs an `alt` attribute, or a binding that supplies dynamic alt text.
4. **Focus states**: never disable focus outlines without providing a visible, high-contrast alternative.

---

## WordPress 7.0 Core Environment

| Component | Requirement / Change |
|---|---|
| **PHP minimum** | WordPress 7.0 and 7.1 run on PHP **7.4 through 8.5**. This skill does not write for that whole range: it targets **PHP 8.4** by default and supports sites on 8.2 and 8.3 through `references/php-compatibility.md`. The site's real PHP version must be established first — see *PHP Requirements* below. |
| **PHPMailer** | Updated to 7.0.2 in WP 7.0. |
| **Code editor** | The CodeMirror bundled for the admin code editor was updated to v5, with Espree replacing Esprima for its JavaScript linting. Nothing to change in a theme's own tooling. |
| **User registration security** | `Administrator` and `Editor` are **removed** from the New User Default Role selector (Settings → General) to prevent accidental privilege escalation. Use the `default_role_dropdown_excluded_roles` filter (`api-allowlist.md → Admin, users, security`) to modify the exclusion list. |

---

## PHP Requirements

### Step 0 — know the PHP version

The default target is **PHP 8.4**. Before writing PHP, establish what the site actually runs (project context file, `php -v` or Tools → Site Health → Info → Server, `composer.json`, `.wp-env.json`, the `Requires PHP:` header — full procedure in `references/php-compatibility.md → §1`).

| Site's PHP | What to do |
|---|---|
| 8.4 or 8.5 | Use everything in this section. Declare `Requires PHP: 8.4` — the theme's floor is the version it was written for. |
| 8.3 or 8.2 | Load `references/php-compatibility.md` and write its fallback for anything marked 8.4 (and, on 8.2, 8.3). Declare the real floor. |
| Unknown and cannot be established | Do **not** guess 8.4 — 8.4 syntax is a parse error on 8.2. Write the 8.2-compatible form, declare `Requires PHP: 8.2`, and state the assumption. |
| 8.1 or older | Out of scope (no longer supported by PHP). Say so. |

Always declare the floor in the `style.css` header (`Requires PHP:`, next to `Requires at least:`) — WordPress refuses to activate the theme on an older PHP.

### Rules for every PHP file

These are pre-write checks on every supported version. Missing any one means the code is rejected and rewritten.

1. `<?php` on line 1.
2. `declare(strict_types=1);` on line 2 (full files only — not excerpt snippets that begin mid-function or with a bare `add_action(` call). **PHP block pattern files that contain only the docblock header and block markup with no function definitions are also exempt** — strict_types has no effect without typed function signatures, so omit it to keep the header uncluttered.
3. Every named function has typed parameters and a return type: `function my_fn( string $arg ): void {}`.
4. Every `add_action()` / `add_filter()` callback is a **named, prefixed function passed by name** — never a closure — so a child theme or plugin can remove it (WordPress coding standards). Closures are for non-hook callbacks only (`render_callback`, `array_map()`, `array_find()`), and they too have typed parameters and return types.
5. Nullable parameters are written out: `?string $text = null`. Never `string $text = null` — implicit nullability is deprecated since 8.4.
6. Every class property is declared. No dynamic properties, no `"${var}"` interpolation, no `(boolean)` / `(integer)` casts — the full "never write" list is `references/php-compatibility.md → §4`.
7. Use `??` (null coalescing) instead of `isset($x) ? $x : ''`.
8. Use `str_contains()`, `str_starts_with()`, `str_ends_with()` instead of `strpos() !== false`.
9. Use `match` expressions instead of `switch` when the result is a single value.
10. Use `?->` null-safe operator for optional chaining on nullable objects.
11. Use `array_is_list()` when checking whether an array is a sequential list.
12. Positional arguments only when calling WordPress functions.

### PHP 8.4 features — use where they fit

Not a quota: use one when it removes code or prevents a bug. Each needs the PHP version shown; on an older site use the fallback in `references/php-compatibility.md → §3`.

| Use | For | Needs |
|---|---|---|
| `array_find()`, `array_find_key()`, `array_any()`, `array_all()` | Replacing `foreach` loops that search or test an array | Any version (WordPress polyfills them) |
| Asymmetric visibility — `public private(set) array $items = array();` | A property everyone may read but only the class may change; replaces a private property plus getter | 8.4 |
| Property hooks — `public string $slug { set => sanitize_title( $value ); }` | Normalising or validating a value at the moment it is assigned. **Not in a project that lints with PHP_CodeSniffer / WordPress Coding Standards** — the tool cannot parse hooks yet; use a setter method there. A hooked property cannot be `readonly`; restrict writes with asymmetric visibility instead. | 8.4 |
| `new My_Theme_Loader()->register();` | Chaining on a new object without wrapping parentheses | 8.4 |
| Typed class constants — `public const string VERSION = '1.0.0';` | Constants whose type must not drift | 8.3 |
| `#[\Override]` | A method that must override a parent's; a typo becomes a compile error | 8.3 |
| `readonly` properties and classes, enums, `#[\SensitiveParameter]` | Value objects; fixed sets of options; parameters carrying API keys | Any supported version (8.2+) |

Most theme code is procedural and needs none of the class features — do not introduce a class just to use them. For rewriting HTML, use WordPress's `WP_HTML_Tag_Processor`, not PHP's DOM classes.

WP snake_case function names and hook names are unchanged. These rules apply to function bodies and signatures only, not to the WordPress API surface.

> **House style vs. modern features:** this section answers "which PHP features to use". For WordPress PHP formatting rules — Yoda conditions, `array()` syntax, tabs, naming conventions, `$wpdb->prepare()`, and prohibited constructs — load `references/php-coding-standards.md`. The two files are complementary.

### Pattern File Header

Every PHP pattern file starts with this header comment (PHP comment, not HTML). WordPress refuses to register a pattern file without `Title` and `Slug`; the other fields are recommended:

```php
<?php
/**
 * Title: Human Readable Title
 * Slug: {{THEME_SLUG}}/pattern-slug
 * Categories: {{THEME_SLUG}}
 * Keywords: keyword1, keyword2
 * Block Types: core/group
 * Inserter: true
 */
?>
```

- `Title` — required; shown in the Block Inserter
- `Slug` — required; must be unique; format: `{{THEME_SLUG}}/{pattern-slug}`
- `Categories` — a registered category slug
- `Inserter: false` — hides the pattern from the inserter (assembler and template-part backing patterns)

### Image URL Pattern (always use PHP)

An image in a pattern is an Image block whose URL is printed by PHP:

```php
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hero.webp' ) ); ?>" alt="<?php esc_attr_e( 'Hero image', '{{TEXT_DOMAIN}}' ); ?>" /></figure>
<!-- /wp:image -->
```

Never hardcode `http://` or relative paths — they break across environments. `get_theme_file_uri()`
returns the child theme's copy of the file when it exists and the parent's otherwise, so the same
pattern works in a parent theme, in a child theme, and when a child overrides one image.

### Pattern Category Registration

Register the theme's pattern category on `init`, before patterns reference it — the code is part of `functions.php` in *Asset Pipeline* below.

---

## Asset Pipeline (functions.php)

### No build step by default

Everything this skill emits runs without a build: PHP patterns, `theme.json`, plain CSS, and native ES modules. A block's `view.js` imports `@wordpress/interactivity` directly — WordPress resolves that specifier through its import map.

Add a build only when the project needs one (TypeScript, SCSS, npm dependencies, a `block.json` block with a React editor script). `@wordpress/scripts` is the supported toolchain for themes and blocks (folder layout: `references/scaffold-tree.md → With a build step`). **Never** emit an `@wordpress/build` configuration for a theme: that package is a pre-1.0 build system for plugins organised as `packages/*`, with its own `package.json` keys, and it does not replace `@wordpress/scripts`.

### How pattern assets load

Three core facts drive the design:

| Mechanism | What it really does |
|---|---|
| `register_block_style( 'core/group', array( 'style_handle' => … ) )` | **Broken in a block theme:** the stylesheet reaches the editor but is never enqueued on the frontend, because core registers its render listener during `wp_head`, after the template has already been rendered (WordPress core ticket #55184, still open on 7.1). Where the listener does run, it matches the block *type*, not the variation. |
| `register_block_style()` | Has no script key. A script cannot be attached to a variation this way. |
| `wp_enqueue_style()` / `wp_enqueue_script()` called while a block renders | Works: in a block theme the template is rendered before `wp_head()`, and core loads its own block assets exactly this way. |

So the theme registers each variation **without** `style_handle` and enqueues its assets from one `render_block` filter that checks the block's `is-style-{slug}` class — the same enqueue-at-render technique `wp_enqueue_block_style()` and WordPress's own tutorials use. The editor canvas gets every pattern stylesheet through `enqueue_block_assets`.

**Rule:** a pattern's CSS and JS are never enqueued directly and never bound with `style_handle`. Add the pattern to the registry below; the loader does the rest. `wp_enqueue_block_style()` is for styles that belong to a block *type* (a global reset for all Groups, a custom block's own stylesheet).

### `inc/pattern-assets.php` — the registry and loader

`{{THEME_PREFIX}}` is the theme slug in snake_case (`my-theme` → `my_theme`). Each registry key is, at once: the folder `patterns/{key}/`, the block style variation name, and the suffix of the `is-style-{key}` class on the pattern's outermost `core/group`.

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Patterns that ship their own assets, keyed by block style variation slug.
 *
 * @return array<string, array{label: string, script?: bool, script_deps?: string[], module?: bool}>
 */
function {{THEME_PREFIX}}_pattern_assets(): array {
	return array(
		'hero-section' => array(
			'label' => __( 'Hero Section', '{{TEXT_DOMAIN}}' ),
		),
		'faq-section'  => array(
			'label'  => __( 'FAQ Section', '{{TEXT_DOMAIN}}' ),
			'script' => true, // Classic script: patterns/faq-section/index.js.
		),
		'product-card' => array(
			'label'  => __( 'Product Card', '{{TEXT_DOMAIN}}' ),
			'module' => true, // Interactivity API store: patterns/product-card/view.js.
		),
	);
}

/**
 * Registers each pattern's block style variation, stylesheet and optional script.
 */
function {{THEME_PREFIX}}_register_pattern_assets(): void {
	$version = wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' );

	foreach ( {{THEME_PREFIX}}_pattern_assets() as $slug => $asset ) {
		$handle = '{{THEME_SLUG}}-' . $slug;
		$folder = '/patterns/' . $slug;

		register_block_style(
			'core/group',
			array(
				'name'  => $slug,
				'label' => $asset['label'],
			)
		);

		wp_register_style( $handle, get_theme_file_uri( $folder . '/style.css' ), array(), $version );
		wp_style_add_data( $handle, 'path', get_theme_file_path( $folder . '/style.css' ) );

		if ( ! empty( $asset['script'] ) ) {
			wp_register_script(
				$handle,
				get_theme_file_uri( $folder . '/index.js' ),
				$asset['script_deps'] ?? array(),
				$version,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		if ( ! empty( $asset['module'] ) ) {
			wp_register_script_module(
				'{{THEME_SLUG}}/' . $slug,
				get_theme_file_uri( $folder . '/view.js' ),
				array( '@wordpress/interactivity' ),
				$version
			);
		}
	}
}
add_action( 'init', '{{THEME_PREFIX}}_register_pattern_assets' );

/**
 * Enqueues a pattern's assets when a block carrying its variation class renders.
 *
 * @param string $block_content Rendered block markup.
 * @param array  $block         Parsed block, including its attributes.
 */
function {{THEME_PREFIX}}_enqueue_pattern_assets( string $block_content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';

	if ( ! is_string( $class_name ) || ! str_contains( $class_name, 'is-style-' ) ) {
		return $block_content;
	}

	$assets = {{THEME_PREFIX}}_pattern_assets();

	foreach ( explode( ' ', $class_name ) as $class ) {
		$slug = str_starts_with( $class, 'is-style-' ) ? substr( $class, 9 ) : '';

		if ( ! isset( $assets[ $slug ] ) ) {
			continue;
		}

		wp_enqueue_style( '{{THEME_SLUG}}-' . $slug );

		if ( ! empty( $assets[ $slug ]['script'] ) ) {
			wp_enqueue_script( '{{THEME_SLUG}}-' . $slug );
		}

		if ( ! empty( $assets[ $slug ]['module'] ) ) {
			wp_enqueue_script_module( '{{THEME_SLUG}}/' . $slug );
		}
	}

	return $block_content;
}
add_filter( 'render_block', '{{THEME_PREFIX}}_enqueue_pattern_assets', 10, 2 );

/**
 * Loads every pattern stylesheet into the editor canvas.
 */
function {{THEME_PREFIX}}_enqueue_pattern_editor_styles(): void {
	if ( ! is_admin() ) {
		return;
	}

	foreach ( array_keys( {{THEME_PREFIX}}_pattern_assets() ) as $slug ) {
		wp_enqueue_style( '{{THEME_SLUG}}-' . $slug );
	}
}
add_action( 'enqueue_block_assets', '{{THEME_PREFIX}}_enqueue_pattern_editor_styles' );
```

### `functions.php`

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// __DIR__, not get_theme_file_path(): each theme must load its own file, even when a child theme ships one with the same name.
require_once __DIR__ . '/inc/pattern-assets.php';

/**
 * Registers the theme's pattern category. Patterns reference it in their Categories header.
 */
function {{THEME_PREFIX}}_register_pattern_categories(): void {
	register_block_pattern_category(
		'{{THEME_SLUG}}',
		array( 'label' => __( '{{THEME_NAME}}', '{{TEXT_DOMAIN}}' ) )
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_pattern_categories' );
```

WordPress does not enqueue a block theme's `style.css` for you. This skill keeps component CSS in `patterns/{slug}/style.css`, so `style.css` normally holds only the header comment and needs no enqueue.

**Child theme addition** — only when the parent theme keeps real rules in its `style.css`:

```php
/**
 * Loads the parent theme's stylesheet. Priority 9 runs it before other callbacks on this hook.
 */
function {{THEME_PREFIX}}_enqueue_parent_style(): void {
	wp_enqueue_style(
		'{{PARENT_THEME_SLUG}}-style',
		get_parent_theme_file_uri( 'style.css' ),
		array(),
		wp_get_theme( '{{PARENT_THEME_SLUG}}' )->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', '{{THEME_PREFIX}}_enqueue_parent_style', 9 );
```

### Key Notes

- **Versions come from the theme's own header.** `wp_get_theme( '{{THEME_SLUG}}' )` — with no argument it returns the active theme, so under a child theme a parent's assets would carry the child's version and a parent update would not bust the cache.
- **One registry entry per pattern.** Slug in the registry = folder name = `is-style-` suffix. A mismatch in any of the three means the CSS silently never loads.
- **The outermost block is a `core/group`.** That is the block type the variations are registered on. Use `tagName` for `section`, `header`, `footer`.
- **Theme file helpers.** `get_theme_file_uri()` / `get_theme_file_path()` return the child theme's copy of a file when it exists, otherwise the parent's — so a parent theme's loader and image paths keep working under a child theme, and the child can override a single file. Use `get_parent_theme_file_uri()` only when the parent's copy is specifically wanted. Do not build asset URLs from `get_stylesheet_directory_uri()` or `get_template_directory_uri()`.
- `wp_style_add_data( …, 'path', … )` lets core inline small stylesheets instead of requesting them. Core inlines the smallest first, up to 40,000 bytes per page in total.
- **Positional arguments only.** WordPress does not support named arguments on core functions; never write `wp_register_style( handle: … )`.
- **Every registered variation shows up in the Group block's Styles panel.** That is expected: it is how the Site Editor exposes the section styles defined under `styles.blocks.core/group.variations` in `theme.json`.
- **Pattern scripts are frontend-only.** The loader never enqueues `index.js` or `view.js` in the editor. Keep the `if ( window.frameElement ) { return; }` guard in `index.js` so it also stays quiet inside same-origin iframe previews.
- **`'script'` vs `'module'`.** `'script' => true` loads `index.js` as a deferred classic script (non-reactive effects). `'module' => true` loads `view.js` as a script module that depends on `@wordpress/interactivity` (reactive stores). A pattern may set both.

---

## Site Editor Sync Behaviour

The Site Editor stores user customisations in the **database**. If a theme file changes but the
editor isn't reflecting it, the database version is taking precedence.

### How to return to the file version

Use the **Reset** action for that template or template part in the Site Editor. For Global Styles the action is **Reset styles**. (The label "Clear customizations" no longer exists.) Resetting discards the database copy, so WordPress reads the theme file again.

Templates and template parts keep revisions. If the user may want any of their Site Editor edits back, have them look through the template's revisions before resetting.

### When this matters most

- After editing a `templates/*.html` or `parts/*.html` file that was already customised in the Site Editor
- After editing `theme.json` styles the user has overridden in Global Styles
- After moving a template part to a new area in `theme.json`
- When old content persists after a file change

A pattern file is different: changing `patterns/*.php` changes what gets inserted from then on. Content already inserted into a page or saved into a customised template is a copy and does not update.

### Programmatic cache busting (development)

The loader versions every asset with the theme's `Version` header. During development, bump that version (or temporarily pass `(string) time()` as the version) to defeat browser caching. Never ship `time()` to production — it disables caching entirely.

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
  → check the `slug` in the part's `wp:pattern` call matches the backing pattern's `Slug` header exactly, and confirm `wp:template-part` is not nested inside a dynamic block's render output
- `headerChildren` with `visible: false` + non-empty `innerHTML` → CSS not loading
  → check the slug is in the pattern-asset registry, matches the folder name, and the block has the `is-style-{slug}` class
- `headerChildren` with `visible: true` → working correctly
