# WordPress API Allowlist

*Before writing any WordPress function or class name in skill output, confirm it appears here.* Every entry below has been verified against developer.wordpress.org. If a name you need is not on this list, look it up before writing — do not invent.

If you're on WP 6.9 or earlier, also check `compatibility-6.9.md` for the items marked "WP 7.0+" — they don't exist on older cores.

## Themes & templates

| Function / class | Notes |
|---|---|
| `register_block_template( $name, $args )` | Available since 6.7. `$args` keys: `title`, `description`, `content`, `post_types`. No `is_ai_ready`. |
| `wp_register_block_template_part()` | Available since 6.7. Companion to `register_block_template` for parts. |
| `WP_Block_Templates_Registry` | Class behind the registration API. |
| `register_block_pattern()` | Registers a pattern from PHP. |
| `register_block_pattern_category()` | Registers a category before patterns reference it. |
| `unregister_block_pattern()` / `unregister_block_pattern_category()` | Removes registrations. |
| `get_template_directory_uri()` | Returns the **parent** theme URI. |
| `get_stylesheet_directory_uri()` | Returns the **child** (or current) theme URI. |
| `get_stylesheet_directory()` | Returns the child theme absolute path (used for `wp_style_add_data( …, 'path', … )`). |
| `wp_get_image_alttext( $attachment_id )` | WP 7.0+. Returns the alt text stored in attachment meta. Use when importing images to pre-populate `core/image` `alt` attributes. |

## Block registration

| Function / class | Notes |
|---|---|
| `register_block_type( $name_or_path, $args )` | Server-side block registration. In WP 7.0, set `'supports' => array( 'autoRegister' => true )` to register a block from PHP alone (no `block.json` / JS). |
| `register_block_type_from_metadata()` | Older form; registers from a `block.json` file path. |
| `register_block_style( $block, $args )` | Registers a block style variation. `$args` accepts `name`, `label`, `style_handle`, `script_module_handle` (since 6.7). |
| `unregister_block_style()` | Removes a previously-registered variation. |
| `wp_enqueue_block_style( $block, $args )` | Enqueues a stylesheet only when the named block is on the page. |
| `core/heading` (WP 7.0 level variations) | WP 7.0 registers H1–H6 as named inserter-level variations of `core/heading`. The block name in markup is always `core/heading` — never `core/headings`. Use `level: 1–6` in attributes; editors can switch levels via the toolbar at any time. |
| `core/navigation-overlay-close` | WP 7.0. Close button for mobile navigation overlays. Must be placed inside a `core/navigation` block that has `overlayMenu` set to `"always"` or `"mobile"`. |

## Block Bindings

| Function / class | Notes |
|---|---|
| `register_block_bindings_source( $name, $args )` | Registers a custom binding source. `$args` keys: `label`, `get_value_callback`, `uses_context`. |
| Built-in sources | `core/post-meta`, `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-author-name`, `core/site-title`, `core/site-tagline`, `core/pattern-overrides`. |
| Filter: `block_bindings_supported_attributes` | WP 7.0+. Allows any custom block to expose attributes to the Pattern Overrides UI. Return an array of attribute names the block exposes. Use this instead of `metadata.bindings` for fully custom blocks. Signature: `apply_filters( 'block_bindings_supported_attributes', array $supported, string $block_name )`. |

## contentOnly Pattern Opt-Out (WP 7.0+)

| API | Notes |
|---|---|
| `disableContentOnlyForUnsyncedPatterns` PHP filter | WP 7.0+. Stable site-wide opt-out. Hook on `block_editor_settings_all` to return `true` for this key in the settings array to disable the contentOnly default globally. Use only when the entire site must bypass contentOnly — rare. |
| `disableContentOnlyForUnsyncedPatterns` JS filter | WP 7.0+. Equivalent client-side opt-out via `wp.hooks.addFilter`. Same scope as the PHP filter. |
| `"__experimentalSettings": { "disableContentOnlyForUnsyncedPatterns": true }` | Experimental per-block attribute. Set on the outermost block's attributes to opt out for one specific pattern only. Preferred for per-pattern developer scaffolds. |

## Block Supports: contentOnly & List View (WP 7.0+)

| Support key | Notes |
|---|---|
| `"role": "content"` on `attributes` | WP 7.0+. Set on individual attribute definitions in `block.json` to mark them as content-editable in contentOnly mode. Without this, the attribute (and possibly the block) is hidden from List View inside contentOnly containers. |
| `"contentRole": true` in `supports` | WP 7.0+. Alternative when no specific content attribute exists. Makes the block visible in List View inside contentOnly containers without tying visibility to a particular attribute. Prefer `"role": "content"` on attributes when possible. |
| `"listView": true` in `supports` | WP 7.0+. Adds a dedicated List View tab to the block inspector for container blocks (blocks with `InnerBlocks`). Allows editors to rearrange and insert inner blocks from the sidebar. Recommended for any custom block wrapping multiple children. |

## Viewport Block Visibility (WP 7.0+)

| Key | Notes |
|---|---|
| `metadata.blockVisibility` | Object on a block's `metadata` attribute. Controls per-device visibility. |
| `metadata.blockVisibility.viewport` | Array of strings. Allowed values: `"mobile"`, `"tablet"`, `"desktop"`. Block renders only on the listed device types. Omit the key entirely to show on all devices. An empty array `[]` hides the block everywhere. |

Enable viewport visibility in `theme.json settings` before using it (per-block or globally):

```json
// Per block
"settings": { "blocks": { "core/group": { "blockVisibility": { "viewport": true } } } }

// Globally (all blocks)
"settings": { "blockVisibility": { "viewport": true } }
```

Block markup example (hidden on mobile):

```html
<!-- wp:group {
  "metadata": {
    "blockVisibility": {
      "viewport": ["tablet", "desktop"]
    }
  }
} -->
<div class="wp-block-group"><!-- hidden on mobile --></div>
<!-- /wp:group -->
```

## Abilities API (WP 7.0+)

| Function / hook | Notes |
|---|---|
| `wp_register_ability( $name, $args )` | Registers an ability. Required `$args`: `label`, `description`, `category`, `execute_callback`. Optional: `input_schema`, `output_schema`, `permission_callback`. |
| `wp_register_ability_category( $slug, $args )` | Registers an ability category. Required `$args`: `label`. Categories must exist before abilities reference them. |
| `wp_unregister_ability()` / `wp_unregister_ability_category()` | Removes registrations. |
| Hook: `wp_abilities_api_init` | Hook your registration callbacks here, not on `init`. |
| Client package: `@wordpress/abilities` | Pure JS state store. |
| Client package: `@wordpress/core-abilities` | WordPress integration layer. |

## WP AI Client (WP 7.0+)

| Function / class | Notes |
|---|---|
| `WP_AI_Client_Prompt_Builder` | Fluent builder; chain `add_system_message()`, `add_user_message()`, then `send()`. Returns `WP_Error` if no connector is configured at Settings → Connectors. |
| Connectors UI | Site admin enables OpenAI / Anthropic / Google credentials at **Settings → Connectors**. |

## Script Modules & Interactivity API

| Function | Notes |
|---|---|
| `wp_register_script_module( $id, $src, $deps, $version )` | Registers a JavaScript module. |
| `wp_enqueue_script_module()` | Enqueues a registered module. Usually automatic when bound via `script_module_handle` on `register_block_style`. |
| `wp_deregister_script_module()` | Removes a registration. |
| Interactivity API store | Use `store()` from `@wordpress/interactivity` in the module. |
| HTML directives | `data-wp-interactive`, `data-wp-context`, `data-wp-on--<event>`, `data-wp-bind--<attr>`, `data-wp-class--<name>`, `data-wp-style--<prop>`, `data-wp-text`. |
| `watch( fn )` from `@wordpress/interactivity` | WP 7.0+. Subscribes to signal changes. `fn` runs once immediately and again whenever any reactive state it reads changes. Returns a teardown cleanup function. |
| HTML directive `data-wp-watch` | WP 7.0+. Declarative form of `watch()`. The value is a callback key in the store (e.g. `data-wp-watch="callbacks.onSaveChange"`). Runs whenever reactive state the callback reads changes. |

## JavaScript Packages (WP 7.0+)

| Package | Notes |
|---|---|
| `@wordpress/boot` | Enables plugins to register custom Site Editor pages with route validation. Import `boot` from this package and call it once during editor initialisation. WP 7.0+. |
| `@wordpress/grid` | Standardised grid toolkit for editor interfaces. Provides `GridLayout`, `GridItem`, and related components. WP 7.0+. |
| `@wordpress/build` | WP 7.0+ build toolchain. Replaces `@wordpress/scripts` webpack+Babel pipeline with esbuild. Run via `npx wp-build`. Auto-generates `.asset.php` registration files from `package.json` `exports` map. Use for themes that compile Script Modules or TypeScript. Not needed for pure-PHP themes. |

## Block Hooks

| Function / filter | Notes |
|---|---|
| Filter: `hooked_block_types` | Returns the list of blocks to attach to a relative block. |
| Filter: `hooked_block_{block-name}` | Per-block-specific hook variant. |
| Manifest: `blockHooks` in `block.json` | Declarative form. |

## Speculation Rules

| API | Notes |
|---|---|
| `<script type="speculationrules">` JSON | Inject via `wp_head` or `wp_footer`. Standard Web Platform feature, no WP-specific wrapper required. |

## Localisation & escaping

| Function | Notes |
|---|---|
| `__( $text, $domain )` | Translate string. |
| `esc_html__( $text, $domain )` | Translate + escape for HTML. |
| `esc_attr__()`, `esc_attr_e()` | Attribute escaping. |
| `esc_html_e()` | Translate + echo + escape. |
| `esc_url()` | Escape a URL for output. |
| `wp_kses_post()` | Sanitise HTML allowing post content tags. |
| `wp_json_encode()` | Safer JSON encoding for output. |

## Site Editor / sync

| Function / behaviour | Notes |
|---|---|
| Site Editor database overrides | Documented behaviour; clear via Appearance → Editor → ⋮ → "Reset to defaults" / "Clear Customizations". No PHP API. |
| Template / Template Part / Pattern Revisions | WP 7.0+. Edit history for templates, template parts, and synced patterns. Access via Site Editor → select entity → ⋮ → "Revisions". Roll back to any saved version. No PHP API — editor-only. Check revisions BEFORE clearing customisations — rolling back is reversible, clearing is not. |
| `wp_register_style()` / `wp_enqueue_style()` | Standard stylesheet registration. |
| `wp_style_add_data( $handle, 'path', $absolute_path )` | Enables WordPress's small-CSS inlining. |

## Names that look real but are not (do not use)

| Wrong | Correct |
|---|---|
| `register_block_ability()` | `wp_register_ability()` |
| `WP_Icons_Registry::get_instance()->register(...)` | Use the Icon Registration API documented at make.wordpress.org/core (the final 7.0 helper signature) or a PHP icon helper as fallback. |
| `'is_ai_ready' => true` on `register_block_template` | Not a real parameter. Use the documented `title`, `description`, `content`, `post_types` keys. |
| `__experimentalRole: 'content'` | Removed. Use `metadata.bindings` + `metadata.name` for Pattern Overrides. |
| `"version": 4` in theme.json | No such version. Use `"version": 3`. |
| `https://schemas.wp.org/wp/7.0/theme.json` | 404. Use `https://schemas.wp.org/trunk/theme.json` or `https://schemas.wp.org/wp/6.6/theme.json`. |
| `@wordpress/viewport-visibility` | Does not exist. Use the `metadata.blockVisibility.viewport` key directly on the block's metadata attribute. |
| `wp_register_font_library_font()` | Does not exist. The Font Library is a UI-only feature managed via Appearance → Editor → Styles → Typography → Manage Fonts, or via `theme.json fontFamilies` for version-controlled registration. |
