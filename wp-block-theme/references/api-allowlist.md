# WordPress API Allowlist

*Before writing any WordPress function or class name in skill output, confirm it appears here.* Every entry below has been verified against developer.wordpress.org. If a name you need is not on this list, look it up before writing — do not invent.

This allowlist targets WordPress 7.0 only. All entries below are confirmed stable in WP 7.0.

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
| `core/navigation-overlay-close` | WP 7.0. Close button for mobile navigation overlays. Must be placed inside a `core/navigation` block that has `overlayMenu` set to `"always"` or `"mobile"`. Always include this block explicitly in custom overlay patterns — omitting it forces a theme-clashing fallback button. |
| `core/accordion` | WP 7.0. Multi-item collapsible disclosure widget. Structure: `core/accordion` → one or more `core/accordion-item` children, each containing `core/accordion-heading` and `core/accordion-panel`. Use for FAQ-style groups with multiple items. For a single collapsible disclosure, use `core/details` instead. |
| `core/details` | WP 6.3+. Single collapsible disclosure using native `<details>`/`<summary>` HTML. Use for one-off expandable sections. For multi-item accordion UIs, use `core/accordion` (WP 7.0). |
| `core/playlist` | WP 7.0. Native audio playlist block with waveform visualization. Use when converting designs that include podcast players or audio track lists. |

## Template Part Areas (WP 7.0)

Valid `area` values for `templateParts` entries in `theme.json` and for `wp:template-part` block attributes:

| Area value | Use |
|---|---|
| `header` | Site header region |
| `footer` | Site footer region |
| `uncategorized` | General-purpose template part (default) |
| `navigation-overlay` | WP 7.0. Mobile navigation overlay. Register area in `theme.json templateParts`; create the `/parts/*.html` file; register a pattern with `Block Types: core/template-part/navigation-overlay`; use slug-only (no theme prefix) in the Navigation block's `overlay` attribute. |

## Breadcrumb Filters (WP 7.0)

| Filter | Notes |
|---|---|
| `block_core_breadcrumbs_items` | Modifies the breadcrumb trail for `core/breadcrumbs`. Receives `array $items, WP_Block $block` — 10, 2 priority. Each item in the array is an associative array with three keys: `label` (string), `url` (string), and `allow_html` (bool). **Sanitization rule:** if `allow_html` is `true` the label is sanitized via `wp_kses_post()`; if `false` or omitted it is escaped via `esc_html()`. Always set `allow_html: false` (or omit it) unless you are intentionally injecting safe HTML such as an SVG icon. |

## Block Bindings

| Function / class | Notes |
|---|---|
| `register_block_bindings_source( $name, $args )` | Registers a custom binding source. `$args` keys: `label`, `get_value_callback`, `uses_context`. |
| Built-in sources | `core/post-meta`, `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-author-name`, `core/site-title`, `core/site-tagline`, `core/pattern-overrides`. |
| Filter: `block_bindings_supported_attributes` | WP 7.0+. Allows any custom block to expose attributes to the Pattern Overrides UI. Return an array of attribute names the block exposes. Use this instead of `metadata.bindings` for fully custom blocks. Signature: `apply_filters( 'block_bindings_supported_attributes', array $supported, string $block_name )`. |

## contentOnly Pattern Opt-Out (WP 7.0+)

| API | Notes |
|---|---|
| `disableContentOnlyForUnsyncedPatterns` key inside `block_editor_settings_all` | WP 7.0+. Stable site-wide opt-out. Hook on `block_editor_settings_all` and return `true` for this key in the settings array. **Never** use `add_filter('disableContentOnlyForUnsyncedPatterns', ...)` — that filter is never called. Correct PHP: `add_filter('block_editor_settings_all', function(array $s): array { $s['disableContentOnlyForUnsyncedPatterns'] = true; return $s; });` Use only when the entire site must bypass contentOnly — rare. |
| `disableContentOnlyForUnsyncedPatterns` JS equivalent | WP 7.0+. `wp.hooks.addFilter('blockEditor.settings', 'my-theme/disable-content-only', (settings) => ({ ...settings, disableContentOnlyForUnsyncedPatterns: true }))` |
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

See `references/ai-client.md` for full coverage. Quick-reference surface:

| Function / class | Notes |
|---|---|
| `wp_ai_client_prompt( $text = '' )` | Entry point. Returns `WP_AI_Client_Prompt_Builder`. Pass prompt text directly or build it via `->with_text()`. |
| `WP_AI_Client_Prompt_Builder` | Fluent builder. Configuration methods: `with_text()`, `with_file()`, `with_history()`, `using_system_instruction()`, `using_temperature()`, `using_max_tokens()`, `using_top_p()`, `using_top_k()`, `using_stop_sequences()`, `using_model_preference()`, `as_output_modalities()`, `as_output_file_type()`, `as_json_response( $schema )`. |
| Generation methods | `generate_text()`, `generate_image()`, `convert_text_to_speech()`, `generate_speech()`, `generate_video()`. Return scalar/array payload or `WP_Error`. |
| Result-form variants | `generate_text_result()`, `generate_image_result()`, `convert_text_to_speech_result()`, `generate_speech_result()`, `generate_video_result()`. Return `GenerativeAiResult` with `getTokenUsage()` (input/output/thinking), `getProviderMetadata()`, `getModelMetadata()`. |
| Feature detection (static) | `WP_AI_Client_Prompt_Builder::is_supported_for_text_generation()`, `::is_supported_for_image_generation()`, `::is_supported_for_text_to_speech_conversion()`, `::is_supported_for_speech_generation()`, `::is_supported_for_video_generation()`. Pure capability checks — no API call, no cost. **Always gate generation calls behind these.** |
| Hook: `wp_connectors_init` | Fires once with `WP_Connector_Registry`. Use to register or modify connectors. |
| `WP_Connector_Registry` | Methods: `is_registered( $id )`, `register( $id, $args )`, `unregister( $id )`. Use inside `wp_connectors_init`. |
| Credential resolution order | 1) env var (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, etc.), 2) PHP constant `define( 'OPENAI_API_KEY', '…' )`, 3) DB option `connectors_ai_{$id}_api_key` (managed at **Settings → Connectors**). First match wins; subsequent sources are ignored. |
| Connectors UI | Site admin enables OpenAI / Anthropic / Google credentials at **Settings → Connectors**. Shows current credential source (env / constant / DB) per connector. |

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

## DataViews & Field API (WP 7.0+)

See `references/dataviews.md` for full coverage. Quick-reference surface:

| API | Notes |
|---|---|
| DataViews `groupBy` | Object form `{ field: string, direction?: 'asc'\|'desc', label?: string }`. Legacy string-only form is deprecated — emit the object form for any new view. |
| DataViews `onReset` | `undefined` → "Reset view" button hidden (view does not persist). `false` → button shown but disabled (persistence active, currently at defaults). `function` → button active; invoked when clicked. Pick by the desired UX state, not by convenience. |
| Field validation rules | Declarative, JSON-Schema-aligned. `pattern` (text/email/tel/url), `minLength`/`maxLength` (text/email/tel/url), `min`/`max` (integer/number). Set on the field definition; validation runs before submit. |
| `getValueFormatted( item, field )` | Custom display function. Use for unit conversion (`2048` → `"2.0 KB"`), date formatting, role labels — anything where the stored value and the visible value differ. |

## User registration (WP 7.0+)

| Filter | Notes |
|---|---|
| `default_role_dropdown_excluded_roles` | Modifies the list of roles excluded from the new-user registration role selector. Default: `['administrator', 'editor']` (added in WP 7.0 to prevent accidental privilege escalation). Return the modified array. Only add a role back if you have a deliberate workflow that requires it. |

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
| `wp_register_icons` filter | Does not exist in WP 7.0. The icon registry is internal/private. Custom theme icons cannot be registered against `core/icon` in WP 7.0 — a public API is planned for WP 7.1. Use inline SVG or `<img>` from a dynamic block instead. |
| `WP_Icons_Registry::get_instance()->register(...)` | Not a public theme/plugin API in WP 7.0. Do not call this directly. Same guidance as above. |
| `'is_ai_ready' => true` on `register_block_template` | Not a real parameter. Use the documented `title`, `description`, `content`, `post_types` keys. |
| `__experimentalRole: 'content'` | Removed. Use `metadata.bindings` + `metadata.name` for Pattern Overrides. |
| `"version": 4` in theme.json | No such version. Use `"version": 3`. |
| `https://schemas.wp.org/trunk/theme.json` | Points to the development branch — validates against unreleased schema features. Use `https://schemas.wp.org/wp/7.0/theme.json` (pinned to the released version) instead. |
| `@wordpress/viewport-visibility` | Does not exist. Use the `metadata.blockVisibility.viewport` key directly on the block's metadata attribute. |
| `wp_register_font_library_font()` | Does not exist. The Font Library is a UI-only feature managed via Appearance → Editor → Styles → Typography → Manage Fonts, or via `theme.json fontFamilies` for version-controlled registration. |
