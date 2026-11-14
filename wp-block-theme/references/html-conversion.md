# HTML → Block Theme Template: Conversion Reference
*Target: WordPress 7.0 and later. Rows marked 7.1+ need a site on 7.1.*

## Table of Contents
1. Block Mapping (Native-First)
2. Full Worked Example (template, patterns, CSS, theme.json, registry, Interactivity, Accordion)
3. CSS Scoping Patterns (including Viewport Visibility)
4. JavaScript Patterns (including Icon Conversion)
5. Image Path Conversion
6. Navigation Conversion
7. Forms and Third-Party Embeds
8. Animations and Scroll Effects
9. CSS Reset Handling
10. Multi-File Input
11. Edge Cases and Gotchas

---

## Block Mapping (Native-First)

Before writing any code, map the static HTML elements to native WordPress Core Blocks.

| Original HTML | Core Block | Implementation |
|---|---|---|
| `<section class="hero">` | `core/group` | Use `tagName: "section"` |
| `<h1>`–`<h6>` | `core/heading` | Set the level with `"level": 1`–`6`. Editors can change it from the block toolbar. The block name is always `core/heading`. |
| `<div class="features">` | `core/group` | Use `layout: { "type": "grid" }` |
| `<a class="btn">` | `core/button` | Use `className: "btn-primary"` |
| `<div class="accordion">` (multi-item FAQ) | `core/accordion` (WP 6.9+) | Multi-item collapsible. Structure: `core/accordion` → `core/accordion-item` children, each with `core/accordion-heading` + `core/accordion-panel`. Markup in *Accordion Conversion* below. |
| `<details>/<summary>` (single toggle) | `core/details` (WP 6.3+) | Single collapsible disclosure using native `<details>`/`<summary>`. Chain multiple for simple FAQ-style lists without the full Accordion block. |
| `<audio>` list / podcast player | `core/audio` per track; `core/playlist` on **7.1+** | `core/playlist` does not exist on 7.0. On 7.1+ see `references/core-7.1-apis.md`. |
| `<div class="card">` | `core/group` | Child of native Grid |
| `SVG Icon` (core set) | `core/icon` (WP 7.0) | `{"icon":"core/{name}"}` with a name from the built-in set (e.g. `core/info`, `core/caution`, `core/check`, `core/star-filled`). An unknown name renders nothing. |
| `SVG Icon` (custom/theme) | 7.0: inline SVG or `<img>`; **7.1+**: `core/icon` | 7.0 has no public icon registration — print the SVG from a dynamic block's `render_callback` or as an `<img>`. On 7.1+ register it with `wp_register_icon()` (`references/core-7.1-apis.md`). |
| Mobile nav close button | `core/navigation-overlay-close` (WP 7.0) | Goes inside the **navigation-overlay template part**, never inside `core/navigation`. See `references/architecture.md → Custom Navigation Overlays`. |
| `<div class="gallery">` / custom lightbox grid | `core/gallery` | Native lightbox, no custom JS: set `"linkTo": "lightbox"` on the gallery and `"lightbox": {"enabled": true}` on each inner `core/image`. The gallery has no `lightbox` attribute of its own. Drop any lightbox library the design shipped. |
| `<nav class="breadcrumbs">` | `core/breadcrumbs` (WP 7.0) | Native breadcrumbs block. Filter the trail via `block_core_breadcrumbs_items` to add/remove/reorder crumbs server-side. Never hand-roll a breadcrumb pattern for a WP 7.0 target. |

Using Core Blocks lets editors change colors, typography, and spacing via the Site Editor's global styles instead of editing CSS. Only fall back to a dynamic block (`register_block_type()` + `render_callback`) for sections that genuinely cannot be expressed with core.

---

## Full Worked Example

### Input: Static HTML file

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>example Landing</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: #0a0a1a; color: #fff; }

    .hero { padding: 120px 40px; text-align: center; background: linear-gradient(135deg, #1a1a2e, #16213e); }
    .hero h1 { font-size: 3.5rem; margin-bottom: 1rem; }
    .hero p  { font-size: 1.25rem; opacity: 0.8; }

    .features { display: grid; grid-template-columns: repeat(3,1fr); gap: 2rem; padding: 80px 40px; }
    .card { background: rgba(255,255,255,0.05); border-radius: 12px; padding: 2rem; }
  </style>
</head>
<body>
  <section class="hero">
    <h1>Unlock Peak Performance</h1>
    <p>Science-backed examples, delivered to your door.</p>
    <a href="/shop" class="btn-primary">Shop Now</a>
  </section>

  <section class="features">
    <div class="card"><h3>Purity</h3><p>99%+ certified purity.</p></div>
    <div class="card"><h3>Speed</h3><p>Next-day UK delivery.</p></div>
    <div class="card"><h3>Support</h3><p>Expert guidance.</p></div>
  </section>

  <script>
    document.querySelectorAll( '.card' ).forEach( ( card ) => {
      card.addEventListener( 'mouseenter', () => card.style.transform = 'translateY(-4px)' );
      card.addEventListener( 'mouseleave', () => card.style.transform = '' );
    } );
  </script>
</body>
</html>
```

### Slug: `example-landing`

---

### Output: `templates/example-landing.html`

> **Note: Site Editor Only Layout**
> The example below hardcodes the `example-landing` pattern directly into the `.html` template. This means the Hero and Features sections can only be edited via the global Site Editor (Appearance > Editor), not the standard Page Editor. 
> To allow content creators to edit this natively in the Page Editor instead, remove the `wp:pattern` from this file, set `Inserter: true` in your pattern header, and instruct the user to insert the pattern dynamically into the page content.

```html
<!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/example-landing"} /-->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
<!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
```

---

### Output: `patterns/example-landing.php` (master)

```php
<?php
/**
 * Title: example Landing Page
 * Slug: {{THEME_SLUG}}/example-landing
 * Categories: {{THEME_SLUG}}
 * Keywords: example, landing, performance
 * Inserter: false
 */
?>
  <!-- wp:pattern {"slug":"{{THEME_SLUG}}/example-landing-hero"} /-->
  <!-- wp:pattern {"slug":"{{THEME_SLUG}}/example-landing-features"} /-->
```

---

### Output: `patterns/example-landing-hero.php`

```php
<?php
/**
 * Title: example Landing — Hero
 * Slug: {{THEME_SLUG}}/example-landing-hero
 * Inserter: false
 */
?>
<!-- wp:group {
    "tagName": "section",
    "className": "is-style-example-landing-hero",
    "lock": {"move": true, "remove": true}
} -->
<section class="wp-block-group is-style-example-landing-hero">
    <!-- wp:heading {"level":1} -->
    <h1 class="wp-block-heading">Unlock Peak Performance</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Science-backed examples, delivered to your door.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons -->
    <div class="wp-block-buttons">
        <!-- wp:button {"className":"btn-primary"} -->
        <div class="wp-block-button btn-primary"><a class="wp-block-button__link wp-element-button" href="/shop">Shop Now</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
```

---

### Output: `patterns/example-landing-features.php`

```php
<?php
/**
 * Title: example Landing — Features
 * Slug: {{THEME_SLUG}}/example-landing-features
 * Inserter: false
 */
?>
<!-- wp:group {"tagName":"section","className":"is-style-example-landing-features","layout":{"type":"grid","columnCount":3},"lock":{"move":true,"remove":true}} -->
<section class="wp-block-group is-style-example-landing-features">
	<!-- wp:group {"className":"card"} -->
	<div class="wp-block-group card">
		<!-- wp:heading {"level":3} -->
		<h3 class="wp-block-heading">Purity</h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p>99%+ certified purity.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"card"} -->
	<div class="wp-block-group card">
		<!-- wp:heading {"level":3} -->
		<h3 class="wp-block-heading">Speed</h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p>Next-day UK delivery.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"card"} -->
	<div class="wp-block-group card">
		<!-- wp:heading {"level":3} -->
		<h3 class="wp-block-heading">Support</h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p>Expert guidance.</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
```

---

### Output: `patterns/example-landing-hero/style.css`

```css
/* ============================================================
   Scoped styles for the Hero pattern.
   ALL rules are prefixed with .is-style-example-landing-hero
   ============================================================ */

.is-style-example-landing-hero {
	background: linear-gradient(135deg, #1a1a2e, #16213e); /* 4. Colors — background before text properties */
	text-align: center;                                     /* 4. Colors & Typography */
}

/* Padding and margin live in theme.json — see the `variations` output below. */

.is-style-example-landing-hero h1 {
	margin-bottom: 1rem;  /* 3. Box model */
	font-size: 3.5rem;    /* 4. Colors & Typography */
	color: #fff;          /* 4. Colors & Typography */
}

.is-style-example-landing-hero p {
	font-size: 1.25rem;  /* 4. Colors & Typography */
	color: #fff;         /* 4. Colors & Typography */
	opacity: 0.8;        /* 4. Colors & Typography */
}

.is-style-example-landing-hero .btn-primary {
	display: inline-block;                                  /* 1. Display */
	margin-top: 2rem;                                       /* 3. Box model */
	padding: 1rem 2.5rem;                                   /* 3. Box model */
	border-radius: 8px;                                     /* 3. Box model */
	background: var(--wp--preset--color--accent, #e94560); /* 4. Colors */
	color: #fff;                                            /* 4. Colors */
	text-decoration: none;                                  /* 4. Typography */
}
```

---

### Output: `patterns/example-landing-features/style.css`

```css
.is-style-example-landing-features .card {
	padding: 2rem;                          /* 3. Box model */
	border-radius: 12px;                    /* 3. Box model */
	background: rgba(255, 255, 255, 0.05); /* 4. Colors */
	transition: transform 0.2s ease;        /* 5. Other */
}

.is-style-example-landing-features .card:hover {
	transform: translateY(-4px);            /* 5. Other */
}
```

> Note: The original `mouseenter` / `mouseleave` script is gone. The hover effect is now this one CSS rule, so the pattern needs no `index.js`.

---

> **Push layout to theme.json.** Padding, margin, and gap belong in `theme.json` Section Styles (variations under `styles.blocks.{block}.variations.{name}`). Reserve `style.css` for things JSON cannot express — gradients, complex transforms, scoped overrides. Pure CSS hover effects on native Grid items are preferred over JS for performance.

### Interactivity API (reactive logic)

Reactive UI (a "Save to Favorites" toggle, a counter, anything driven by state) needs `data-wp-*` directives in the markup. They cannot be typed into a core block in a pattern file: the editor validates a core block's saved HTML against what the block generates, so an extra attribute is flagged as invalid content. There are two routes, both detailed in `references/architecture.md → Script Modules: loading Interactivity code`:

- **Route A (preferred)** — the interactive element is a core block the pattern already contains (the design's button, list, group). Add the directives at render time with `WP_HTML_Tag_Processor`, put the store in `patterns/{slug}/view.js`, and set `'module' => true` on the pattern's registry entry. The section stays fully editable.
- **Route B** — the markup is something core blocks cannot express. Print it from a **PHP-only dynamic block** and let core load the block's script module. That is the example below.

**1. Block (`inc/blocks/favorite-toggle.php`):**

```php
<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Favorite Toggle block and its script module.
 */
function {{THEME_PREFIX}}_register_favorite_toggle_block(): void {
	wp_register_script_module(
		'{{THEME_SLUG}}/favorite-toggle',
		get_theme_file_uri( '/inc/blocks/favorite-toggle/view.js' ),
		array( '@wordpress/interactivity' ),
		wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' )
	);

	register_block_type(
		'{{THEME_SLUG}}/favorite-toggle',
		array(
			'title'                  => __( 'Favorite Toggle', '{{TEXT_DOMAIN}}' ),
			'category'               => 'theme',
			'supports'               => array(
				'autoRegister'  => true,
				'interactivity' => true,
			),
			'view_script_module_ids' => array( '{{THEME_SLUG}}/favorite-toggle' ),
			'render_callback'        => static function (): string {
				return sprintf(
					'<div %1$s data-wp-interactive="{{THEME_SLUG}}/favorite-toggle" %2$s><button type="button" data-wp-on--click="actions.toggle" data-wp-bind--aria-pressed="context.isSaved" data-wp-class--is-saved="context.isSaved">%3$s</button></div>',
					get_block_wrapper_attributes(),
					wp_interactivity_data_wp_context( array( 'isSaved' => false ) ),
					esc_html__( 'Save for later', '{{TEXT_DOMAIN}}' )
				);
			},
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_favorite_toggle_block' );
```

**2. Store (`inc/blocks/favorite-toggle/view.js`):**

```js
import { store, getContext } from '@wordpress/interactivity';

store( '{{THEME_SLUG}}/favorite-toggle', {
	actions: {
		toggle() {
			const context = getContext();
			context.isSaved = ! context.isSaved;
		},
	},
} );
```

**3. Use it in a pattern:** `<!-- wp:{{THEME_SLUG}}/favorite-toggle /-->`

`view_script_module_ids` makes core enqueue the module whenever the block renders — no loader code. `supports.interactivity` makes core process the directives on the server. Context is always read with `getContext()` inside the store; there is no global `context` variable. PHP-only blocks are meant for simple server-rendered blocks — reach for route A first.

### Accordion Conversion (WP 6.9+)

Replace static FAQ HTML with the native `core/accordion` block. The question text lives in the `core/accordion-heading` block, not on the item:

```html
<!-- wp:accordion -->
<div role="group" class="wp-block-accordion">
<!-- wp:accordion-item -->
<div class="wp-block-accordion-item">
<!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">What is the purity?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel">
<!-- wp:paragraph -->
<p>Our products are 99% certified purity.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:accordion-panel -->
</div>
<!-- /wp:accordion-item -->
</div>
<!-- /wp:accordion -->
```

Repeat the `wp:accordion-item` block for each question. Add `{"autoclose":true}` to `wp:accordion` to keep one item open at a time, and `{"openByDefault":true}` to an item (with class `is-open` on its `<div>`) to start it open. For a single standalone toggle use `core/details` instead.

---

### Output: `theme.json` additions

The template registration:

```json
"customTemplates": [
  {
    "name": "example-landing",
    "title": "example Landing Page",
    "postTypes": ["page"]
  }
]
```

The spacing the original CSS set with `padding` and `gap`, as section styles on the two variations (merge into the existing `styles` object):

```json
"styles": {
  "blocks": {
    "core/group": {
      "variations": {
        "example-landing-hero": {
          "spacing": {
            "padding": { "top": "120px", "right": "40px", "bottom": "120px", "left": "40px" }
          }
        },
        "example-landing-features": {
          "spacing": {
            "padding": { "top": "80px", "right": "40px", "bottom": "80px", "left": "40px" },
            "blockGap": "2rem"
          }
        }
      }
    }
  }
}
```

---

### Output: pattern-asset registry additions

The theme's pattern-asset loader (`inc/pattern-assets.php`, full file in `references/architecture.md → Asset Pipeline`) registers each variation and enqueues its CSS only where that variation renders. Converting a design adds one entry per sub-pattern to the registry — nothing else:

```php
function {{THEME_PREFIX}}_pattern_assets(): array {
	return array(
		// …existing entries…
		'example-landing-hero'     => array(
			'label' => __( 'Example Landing — Hero', '{{TEXT_DOMAIN}}' ),
		),
		'example-landing-features' => array(
			'label' => __( 'Example Landing — Features', '{{TEXT_DOMAIN}}' ),
		),
	);
}
```

Each key must match three things exactly: the folder `patterns/{key}/`, the file `patterns/{key}/style.css`, and the `is-style-{key}` class on the sub-pattern's outermost block. Add `'script' => true` only when the sub-pattern ships a `patterns/{key}/index.js` (non-reactive), and `'module' => true` only when it ships a `patterns/{key}/view.js` (Interactivity API store).

---

## CSS Scoping Patterns

### What to strip from the original CSS

Remove these before scoping — they will conflict with WordPress global styles:

```css
/* REMOVE — resets that break WP */
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: ...; }
html { scroll-behavior: smooth; }

/* REMOVE — font-face declarations (move to theme.json fontFamilies instead) */
@font-face { font-family: 'Inter'; src: url(...); }

/* KEEP — everything else, just add the scope prefix */
.hero { ... }
```

### What to keep (just prefix it)

```css
/* Keep layout, color, spacing — just prefix */
.is-style-example-landing-hero { ... }
.is-style-example-landing-features .card { ... }
```

### Handling `:root` variables

```css
/* Original */
:root {
  --accent: #e94560;
  --bg: #0a0a1a;
}

/* Converted — move to theme.json color palette OR scope under the component */
.is-style-example-landing-hero {
  --accent: #e94560;
  --bg: #0a0a1a;
}
```

Prefer moving design tokens to `theme.json` color/spacing palettes so they become available
throughout the editor. Keep them as CSS custom properties on the wrapper only if they are
too template-specific.

### Media queries — no change needed

Media queries don't need the wrapper prefix added to the query itself, only to the selectors
within:

```css
/* Correct */
@media (max-width: 768px) {
  /* No manual grid column rules needed in 2026. */
  /* Native WP Grid handles responsiveness via layout attributes. */
}
```

### Viewport Visibility (WP 7.0)

To show or hide a whole block by device, set `metadata.blockVisibility.viewport` instead of writing `display: none` breakpoints. The value is an **object**; the block is hidden on each viewport whose value is `false`. Nothing needs enabling in `theme.json`.

Hiding is CSS-based: the block stays in the page source on every device. Never use it for access control.

**Before (hand-written breakpoint — replace this):**

```css
.desktop-only-section { display: none; }
@media (min-width: 1024px) { .desktop-only-section { display: block; } }
```

**After (desktop only):**

```html
<!-- wp:group {"metadata":{"blockVisibility":{"viewport":{"mobile":false,"tablet":false}}}} -->
<div class="wp-block-group">
  <!-- Hidden by CSS on mobile and tablet. Still present in the DOM. -->
</div>
<!-- /wp:group -->
```

Core's breakpoints are fixed on 7.0: mobile up to 480px, tablet up to 782px, desktop above. A design that switches at another width keeps its own scoped media query.

---

## JavaScript Patterns

### DOMContentLoaded wrapper

Always wrap in this guard — WordPress sometimes loads scripts before the DOM is fully parsed:

```js
document.addEventListener( 'DOMContentLoaded', function () {
	// your code
} );
```

### Scoping JS selectors

If the original JS uses broad selectors like `document.querySelectorAll('.card')`, scope them
to the pattern's variation class so they cannot match anything else on the page:

```js
document.addEventListener( 'DOMContentLoaded', function () {
	const wrapper = document.querySelector( '.is-style-example-landing-features' );
	if ( ! wrapper ) {
		return;
	}

	wrapper.querySelectorAll( '.card' ).forEach( ( card ) => {
		card.classList.add( 'is-ready' );
	} );
} );
```

### Keeping scripts out of previews

The pattern-asset loader never enqueues `index.js` in the editor, so a pattern script only ever
runs on the frontend. Keep one guard so it also stays quiet when the page is displayed inside a
same-origin iframe preview:

```js
document.addEventListener( 'DOMContentLoaded', function () {
	if ( window.frameElement ) {
		return;
	}
	// animations here
} );
```

### Icon Conversion

`core/icon` (WP 7.0) renders a named SVG. Which names exist depends on the WordPress version.

**Built-in icons (7.0 and later).** Names are `core/{name}`; the set includes `core/info`, `core/caution`, `core/error`, `core/check`, `core/star-filled`, `core/star-empty`, `core/arrow-right`, `core/menu`, `core/home`, `core/cart`. A name outside the set renders nothing, with no error.

```html
<!-- wp:icon {"icon":"core/star-filled"} /-->
```

**Custom icons on 7.1+.** Register a collection and the icon, then use it like a built-in one — see `references/core-7.1-apis.md → SVG Icon API`.

**Custom icons on 7.0.** There is no public registration. Print the SVG yourself:

```php
// Option A — inline SVG from a dynamic block's render_callback
'render_callback' => static function (): string {
	$icon_path = get_theme_file_path( '/assets/icons/warning.svg' );
	$svg       = file_exists( $icon_path ) ? (string) file_get_contents( $icon_path ) : '';

	return wp_kses(
		$svg,
		array(
			'svg'  => array(
				'xmlns'       => true,
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'fill'        => true,
				'aria-hidden' => true,
				'role'        => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		)
	);
},
```

```php
<!-- Option B — an Image block inside a PHP pattern -->
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/warning.svg' ) ); ?>" alt="<?php esc_attr_e( 'Warning', '{{TEXT_DOMAIN}}' ); ?>" /></figure>
<!-- /wp:image -->
```

Do NOT write `<!-- wp:icon {"icon":"{{THEME_SLUG}}/warning"} /-->` for a 7.0 site — the name is unregistered and the block renders empty.

---

## Image Path Conversion

### Static HTML path → PHP dynamic path

Whatever the original path looked like, the converted image is an Image block inside a PHP pattern,
with its URL resolved by `get_theme_file_uri()`:

```html
<!-- Original (any of these) -->
<img src="images/hero.webp" alt="Hero">
<img src="/assets/hero.webp" alt="Hero">
<img src="https://my-old-site.com/hero.webp" alt="Hero">
```

```php
<!-- Converted -->
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hero.webp' ) ); ?>" alt="<?php esc_attr_e( 'Hero', '{{TEXT_DOMAIN}}' ); ?>" /></figure>
<!-- /wp:image -->
```

### Background images in inline styles

An inline `style="background-image: …"` attribute cannot be carried into a core block — the
block's saved HTML would no longer match what the block generates. Move the image into the
pattern's scoped stylesheet (next section).

### Background images in CSS

Keep these in the scoped CSS file — no PHP needed. Relative URLs resolve against the stylesheet's own location, and core rewrites them when it inlines the file:

```css
.is-style-example-landing-hero {
	background-image: url("../../assets/images/bg.jpg");
	/* Relative to the CSS file: patterns/example-landing-hero/style.css → ../.. = theme root */
}
```


---

## Navigation Conversion

A static `<nav>` bar in the HTML design should be evaluated case-by-case:

| Scenario | Recommended approach |
|---|---|
| Template is a full-page landing (no WP nav needed) | Render the static nav from a dynamic block registered via `register_block_type()` + `render_callback`. |
| Template should use the site's WP menus | Replace with `<!-- wp:navigation /-->` block |
| Template needs a custom fixed nav | Keep in a dedicated `{slug}-nav.php` sub-pattern |
| Template has a custom mobile hamburger/overlay menu (WP 7.0) | `core/navigation` with `"overlayMenu": "mobile"` plus a `navigation-overlay` template part that contains `core/navigation-overlay-close` |

**Using native WP navigation block:**

```html
<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","justifyContent":"space-between"}} /-->
```

With no `ref` attribute the block falls back to an existing navigation menu — managed in the Site Editor under Navigation; a block theme has no Appearance → Menus screen — or to a list of pages.

**Custom mobile overlay (WP 7.0):** the overlay is a template part, and the close button lives in it. The Navigation block points at the part by slug:

```html
<!-- wp:navigation {"overlayMenu":"mobile","overlay":"mobile-menu"} /-->
```

Full setup (theme.json area, the `parts/mobile-menu.html` file, overlay patterns): `references/architecture.md → Custom Navigation Overlays`. `core/navigation-overlay-close` is not an allowed child of `core/navigation` — never nest it there.

---

## Forms and Third-Party Embeds

### Contact forms (CF7, WPForms, Gravity Forms)

Do **not** paste raw form HTML into a block. Use a shortcode block instead:

```html
<!-- wp:shortcode -->
[contact-form-7 id="123" title="Contact Form"]
<!-- /wp:shortcode -->
```

### iframes (Google Maps, Calendly, etc.)

Render iframes from a PHP-only dynamic block. The `render_callback` returns the iframe markup; with `autoRegister` the block is insertable and previews in the editor, and its `url` attribute gets an inspector control:

```html
<!-- wp:{{THEME_SLUG}}/map-embed {"url":"https://www.google.com/maps/embed?pb=example"} /-->
```

```php
/**
 * Registers the Map Embed block.
 */
function {{THEME_PREFIX}}_register_map_embed_block(): void {
	register_block_type(
		'{{THEME_SLUG}}/map-embed',
		array(
			'title'           => __( 'Map Embed', '{{TEXT_DOMAIN}}' ),
			'category'        => 'embed',
			'supports'        => array( 'autoRegister' => true ),
			'attributes'      => array(
				'url' => array(
					'type'    => 'string',
					'default' => '',
					'role'    => 'content',
				),
			),
			'render_callback' => static function ( array $attributes ): string {
				$url = $attributes['url'] ?? '';

				if ( '' === $url ) {
					return '';
				}

				return sprintf(
					'<div %1$s><iframe src="%2$s" width="100%%" height="400" loading="lazy" allowfullscreen></iframe></div>',
					get_block_wrapper_attributes(),
					esc_url( $url )
				);
			},
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_map_embed_block' );
```

### Video embeds

Prefer the native WP embed block for YouTube/Vimeo:

```html
<!-- wp:embed {"url":"https://youtu.be/abc123","type":"video","providerNameSlug":"youtube"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube">
  <div class="wp-block-embed__wrapper">
    https://youtu.be/abc123
  </div>
</figure>
<!-- /wp:embed -->
```

For `<video>` tags with local sources, render the markup from a dynamic block's `render_callback`.

---

## Animations and Scroll Effects

### CSS animations (preferred)

Move CSS `@keyframes` and `animation` properties into the scoped stylesheet. They work fine:

```css
@keyframes fadeUp {
	from {
		opacity: 0;
		transform: translateY(20px);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

.is-style-example-landing-hero h1 {
	animation: fadeUp 0.6s ease forwards;
}
```

### Intersection Observer (scroll-triggered)

Works fine in `index.js`. Mark the elements to animate with a class — add `js-animate` through each
block's `className` (a `data-*` attribute cannot be typed into core block markup):

```js
document.addEventListener( 'DOMContentLoaded', function () {
	if ( window.frameElement ) {
		return;
	}

	const observer = new IntersectionObserver( ( entries ) => {
		entries.forEach( ( entry ) => {
			if ( entry.isIntersecting ) {
				entry.target.classList.add( 'in-view' );
				observer.unobserve( entry.target );
			}
		} );
	}, { threshold: 0.15 } );

	const wrapper = document.querySelector( '.is-style-example-landing-features' );
	if ( ! wrapper ) {
		return;
	}
	wrapper.querySelectorAll( '.js-animate' ).forEach( ( el ) => observer.observe( el ) );
} );
```

### GSAP / external animation libraries

Register the library once (do not enqueue it) and name it as a dependency of the pattern's script in the registry. It then loads only on pages where that pattern renders:

```php
/**
 * Registers GSAP so pattern scripts can depend on it.
 */
function {{THEME_PREFIX}}_register_gsap(): void {
	wp_register_script(
		'gsap',
		'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js',
		array(),
		'3.12.2',
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_gsap' );
```

```php
// In {{THEME_PREFIX}}_pattern_assets():
'example-landing-hero' => array(
	'label'       => __( 'Example Landing — Hero', '{{TEXT_DOMAIN}}' ),
	'script'      => true,
	'script_deps' => array( 'gsap' ),
),
```

---

## CSS Reset Handling

The most dangerous part of any HTML→WordPress conversion is CSS resets. WordPress injects its
own global styles; a `* { margin: 0; box-sizing: border-box; }` rule carried over from the design —
even scoped under the variation class — overrides the spacing the block editor's controls set.

### Rules to always strip

```css
/* ALWAYS STRIP before scoping */
*, *::before, *::after { ... }
html { ... }
body { ... }
:root { ... }  /* move variables to theme.json or onto the variation class */
```

### Rules that are borderline — check before keeping

```css
/* Check: heading resets affect WP editor headings */
h1, h2, h3, h4, h5, h6 { margin: 0; }

/* Safe IF scoped */
.is-style-example-landing-hero h1 { margin: 0; }
```

### Transition for designers new to WordPress

Tell designers: "Your reset CSS is now WordPress's job. We only style what's inside our wrapper."

---

## Multi-File Input

When the user provides separate HTML, CSS, and JS files:

1. Parse the HTML to find `<link rel="stylesheet">` and `<script src="">` tags.
2. Those referenced files are the CSS/JS to convert.
3. Any additional CDN links (fonts, icon libraries) need to be enqueued in `functions.php`:

```php
// Example: user's HTML had <link href="https://fonts.googleapis.com/css2?family=Inter...">
function {{THEME_PREFIX}}_example_landing_fonts( string $block_content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';

	// A master pattern has no class of its own, so key on one of its sub-patterns.
	if ( is_string( $class_name ) && str_contains( $class_name, 'is-style-example-landing-hero' ) ) {
		wp_enqueue_style(
			'example-landing-google-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap',
			array(),
			null
		);
	}
	return $block_content;
}
add_filter( 'render_block', '{{THEME_PREFIX}}_example_landing_fonts', 10, 2 );
```

Better: download the fonts locally and register them in `theme.json fontFamilies` instead.

---

## Edge Cases and Gotchas

### `<head>` meta tags

Ignore `<title>`, `<meta>`, `<link rel="icon">` etc. from the HTML — WordPress handles these.
Only take content from within the `<body>` tag.

### Inline SVGs

For icons, follow *Icon Conversion* above. For a one-off decorative shape, emit the SVG from a dynamic block's `render_callback` rather than pasting it raw into a template:

```html
<!-- wp:{{THEME_SLUG}}/decorative-svg /-->
```

### `<script type="application/ld+json">` (structured data)

Print it from PHP on `wp_footer`, only on pages where the pattern renders:

```php
/**
 * Prints the Organization structured data.
 */
function {{THEME_PREFIX}}_print_structured_data(): void {
	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => get_bloginfo( 'name' ),
	);

	echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>';
}

/**
 * Queues the structured data once, on pages where the template's pattern renders.
 */
function {{THEME_PREFIX}}_queue_structured_data( string $block_content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';

	if ( is_string( $class_name ) && str_contains( $class_name, 'is-style-example-landing-hero' ) && ! has_action( 'wp_footer', '{{THEME_PREFIX}}_print_structured_data' ) ) {
		add_action( 'wp_footer', '{{THEME_PREFIX}}_print_structured_data' );
	}

	return $block_content;
}
add_filter( 'render_block', '{{THEME_PREFIX}}_queue_structured_data', 10, 2 );
```

### Sections that need dynamic WP data

If a section needs the latest posts, a product list, or a custom field value, it cannot be raw HTML. Options:

1. Use a native WP block (Query Loop, Post Title, etc.)
2. Use a shortcode: `[my_custom_shortcode]` inside `<!-- wp:shortcode -->`
3. Register a PHP-only dynamic block (`references/architecture.md → Dynamic Blocks (PHP-only)`)

### CSS `calc()` and `clamp()` — no changes needed

These work as-is inside scoped CSS:

```css
.is-style-example-landing-hero h1 {
  font-size: clamp(2rem, 5vw, 4rem);
}
```

### Tailwind CSS utility classes in the HTML

If the HTML uses Tailwind classes (`text-xl`, `flex`, `gap-4`), you have two options:

1. **Strip Tailwind, rewrite with vanilla CSS** — preferred for long-term maintainability.
2. **Keep the utility classes for a prototype only** — load Tailwind's browser build on that one
   template. It is a script, not a stylesheet, and adds a large payload.

Never load Tailwind globally — its reset styles break the WordPress editor UI.
