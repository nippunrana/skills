# What WordPress 7.1 adds

Everything in the other reference files works on WordPress 7.1. This file lists what exists **only on 7.1 and later**. Nothing here may appear in output for a site that still runs 7.0.

**Load this file when**: the site runs WordPress 7.1 or later and the task involves custom icons, the Playlist or Tabs blocks, responsive (per-viewport) block styles, viewport breakpoints, or the newer `theme.json` keys below.

**Establish the version first.** Read it from the project context file, from `wp-includes/version.php` in the installation, or ask. If it is unknown, write 7.0-compatible output and say that a 7.1 site could use the feature below.

When a theme's minimum is 7.1, pin `"$schema": "https://schemas.wp.org/wp/7.1/theme.json"`.

---

## 1. SVG Icon API

7.1 adds public registration for icons. A registered icon appears in the Icon block's picker and renders on the frontend.

```php
/**
 * Registers the theme's icon collection and icons.
 */
function {{THEME_PREFIX}}_register_icons(): void {
	// 1. A collection must exist before icons are added to it.
	wp_register_icon_collection(
		'{{THEME_SLUG}}',
		array(
			'label' => __( '{{THEME_NAME}} Icons', '{{TEXT_DOMAIN}}' ),
		)
	);

	// 2. Register each icon as collection/icon-name.
	wp_register_icon(
		'{{THEME_SLUG}}/warning',
		array(
			'label'     => __( 'Warning', '{{TEXT_DOMAIN}}' ),
			'file_path' => get_theme_file_path( 'assets/icons/warning.svg' ),
		)
	);
}
add_action( 'init', '{{THEME_PREFIX}}_register_icons' );
```

| Function | Notes |
|---|---|
| `wp_register_icon_collection( $slug, $args )` | `$args`: `label` (required), `description`. Returns `bool`. |
| `wp_register_icon( $icon_name, $args )` | Name is `collection/icon-name`, lowercase letters, digits, hyphens, underscores. `$args`: `label` (required) and **exactly one** of `content` (SVG markup string) or `file_path` (absolute path to an `.svg`). Returns `bool`. |
| `wp_unregister_icon()`, `wp_unregister_icon_collection()` | Remove registrations. |
| `wp_get_icon( $name, $args = array() )` | Returns the SVG markup, or an empty string when the icon is unknown. `$args`: `size` (pixels), `class`, `label` (accessible label). Use it in a `render_callback` or pattern PHP. |

Markup:

```html
<!-- wp:icon {"icon":"{{THEME_SLUG}}/warning"} /-->
```

The SVG is sanitised against a small allowlist. A `file_path` is read lazily, so a wrong path shows up as an empty icon, not a registration error.

On 7.0 none of these functions exist: use a built-in `core/*` icon, or print the SVG from a `render_callback` / as an `<img>`.

---

## 2. New blocks

| Block | Structure |
|---|---|
| `core/playlist` | Contains `core/playlist-track` blocks. |
| `core/tabs` | Contains one `core/tab-list` and one `core/tab-panels`; `core/tab-panels` contains `core/tab-panel` blocks (attribute `label`). |

Build these in the editor and copy the serialised markup into the pattern. Do not hand-write their markup from memory.

---

## 3. The post editor is always iframed

On 7.0 the post editor falls back to a non-iframed canvas when any block on the page uses Block API version 2 or lower. On 7.1 it is always iframed, including on sites with legacy meta boxes.

Editor-side code must not reach the canvas through the global `document` / `window` — those belong to the admin page. Get the canvas document from an element inside it (`element.ownerDocument`, `ownerDocument.defaultView`).

---

## 4. theme.json additions

| Key | Notes |
|---|---|
| `styles.blocks.{block}."@mobile"` / `"@tablet"` | Responsive block styles. The same style object as the block's base styles, applied inside that viewport's range. Pseudo-states nest inside: `"@mobile": { ":hover": { … } }`. |
| `settings.viewport` | Top-level only. `{ "mobile": "30rem", "tablet": "45rem" }` sets the breakpoint widths used by responsive styles **and** by block visibility. Defaults: mobile ≤ 480px, tablet ≤ 782px. |
| `settings.blockVisibility.allowEditing` | Top-level only. `false` hides the block visibility controls. Saved visibility attributes still render. |
| Pseudo-states on `core/navigation-link` | `":hover"`, `":focus"`, `":focus-visible"`, `":active"` — 7.0 supports them on `core/button` only. |
| `settings.background.gradient` | Enables a gradient background control. |
| `settings.dimensions.minWidth` | Enables a minimum-width control. |
| `styles…typography.textShadow` | Sets `text-shadow`. |

```json
{
	"settings": {
		"viewport": { "mobile": "30rem", "tablet": "45rem" }
	},
	"styles": {
		"blocks": {
			"core/group": {
				"spacing": { "padding": { "top": "3rem", "bottom": "3rem" } },
				"@mobile": {
					"spacing": { "padding": { "top": "1rem", "bottom": "1rem" } }
				}
			}
		}
	}
}
```

To remove the responsive-editing controls for editors, set the `responsiveEditingEnabled` editor setting to `false` through the `block_editor_settings_all` filter.

---

## 5. Abilities API

See `references/abilities-api.md → §7` (`meta.public`, filtered `wp_get_abilities()`, execution filters).

---

## 6. Still not in 7.1

Real-time collaboration is not enabled in 7.1. The rule in `references/core-7.0-apis.md → §1` applies unchanged.

For anything not covered here, check the WordPress 7.1 Field Guide (make.wordpress.org/core) before writing code — do not infer an API from a feature name.
