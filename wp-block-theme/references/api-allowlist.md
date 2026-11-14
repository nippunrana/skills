# WordPress API Allowlist

*The checked list for everything specific to WordPress 6.5–7.1. Long-established basics (`add_action()`, `is_admin()`, `esc_url_raw()`, `core/group`) need no entry; anything you are not certain of must be confirmed here, in core source, or on developer.wordpress.org before you write it.*

The functions, hooks, classes, block names and `theme.json` keys below were checked against the WordPress core source at tags **7.0.8** and **7.1.4**. "Since" is the version that introduced it. Entries marked **7.1+** do not exist on a 7.0 site — see `references/core-7.1-apis.md`. WooCommerce APIs are not listed here; they live in the `woocommerce*.md` references.

If a name you need is not listed, look it up in core source or on developer.wordpress.org before writing it. Do not invent, and do not trust a name because it sounds plausible — the last section lists plausible names that do not exist.

## Themes & templates

| Function / class | Notes |
|---|---|
| `register_block_template( $template_name, $args )` | Since 6.7. For plugins. `$template_name` is `namespace//template_name` — two slashes, lowercase — e.g. `{{THEME_SLUG}}//template_name`. `$args` keys: `title`, `description`, `content`, `post_types`, `plugin`. An invalid name triggers `_doing_it_wrong()` and returns `WP_Error` — the template is not registered. |
| `unregister_block_template( $template_name )` | Since 6.7. |
| `register_block_pattern( $name, $properties )` | Registers a pattern from PHP. Patterns in a theme's `patterns/` folder register automatically. |
| `register_block_pattern_category( $name, $properties )` | Register a category before patterns reference it. |
| `unregister_block_pattern()` / `unregister_block_pattern_category()` | Remove registrations. |
| `get_theme_file_uri( $file )` / `get_theme_file_path( $file )` | Since 4.7. **The default for every asset URL and path.** Resolve a theme-relative file: the child theme's copy if it exists, otherwise the parent's. |
| `get_parent_theme_file_uri( $file )` / `get_parent_theme_file_path( $file )` | Since 4.7. Always the parent theme's copy. |
| `get_stylesheet_directory_uri()` / `get_stylesheet_directory()` | Active (child, if any) theme directory. Valid, but a parent theme's assets disappear under a child theme — prefer the helpers above. |
| `get_template_directory_uri()` | Parent theme directory URI. Prefer `get_parent_theme_file_uri()`. |
| `wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' )` | The theme's own version, for asset cache-busting. Pass the slug: with no argument `wp_get_theme()` returns the *active* theme, which is the child when one is active. |
| `style.css` headers `Requires at least` / `Requires PHP` | WordPress checks both on activation and refuses to switch to the theme when either is not met. |
| Plugin header `Requires Plugins` | Since 6.5. Comma-separated slugs of the plugins a plugin depends on, for example `woocommerce`. |

Pattern file headers core reads from `patterns/*.php`: `Title`, `Slug`, `Description`, `Viewport Width`, `Inserter`, `Categories`, `Keywords`, `Block Types`, `Post Types`, `Template Types`. There is no "synced" header — a pattern shipped as a theme file is always unsynced.

## Block registration & assets

| Function / hook | Notes |
|---|---|
| `register_block_type( $block_type, $args )` | Server-side registration. Useful `$args`: `title`, `description`, `category`, `attributes`, `supports`, `render_callback`, `view_script_module_ids` (script module IDs enqueued when the block renders, since 6.5). Set `'supports' => array( 'autoRegister' => true )` (since 7.0) to register a block from PHP alone — see `references/architecture.md → Dynamic Blocks (PHP-only)`. |
| `register_block_type_from_metadata()` | Registers from a `block.json` path. |
| `get_block_wrapper_attributes( $extra_attributes = array() )` | Since 5.6. Call inside a `render_callback` to output the block's classes, styles and anchor on its wrapper element. |
| `register_block_style( $block_name, $style_properties )` | Registers a block style variation (`is-style-{name}`). Keys: `name`, `label`, `inline_style`, `style_handle`, `is_default`, `style_data`. **There is no script key.** **Do not use `style_handle` in a block theme:** the stylesheet loads in the editor but not on the frontend (core ticket #55184, open on 7.0 and 7.1). |
| `unregister_block_style( $block_name, $block_style_name )` | Removes a variation. |
| `wp_enqueue_block_style( $block_name, $args )` | Since 5.9. Enqueues a stylesheet when the named block type is on the page. `$args`: `handle`, `src`, `path`, `deps`, `ver`, `media`. |
| `wp_register_style()` / `wp_enqueue_style()` | Standard stylesheet registration. Calling `wp_enqueue_style()` while a block renders is how core loads block CSS on demand. |
| `wp_style_add_data( $handle, 'path', $absolute_path )` | Lets core inline the stylesheet. Core inlines the smallest stylesheets first, up to 40,000 bytes in total per page (filter `styles_inline_size_limit`). |
| `wp_register_script()` / `wp_enqueue_script()` | Classic scripts. `$args` array keys: `in_footer`, `strategy` (`defer` / `async`), `fetchpriority`, and (since 7.0) `module_dependencies` — a classic script that needs a script module must be in the footer or deferred. |
| Filter `render_block` | `( string $block_content, array $block, WP_Block $instance )`. Runs for every rendered block; `$block['attrs']['className']` holds the block's classes. `render_block_{$block_name}` (e.g. `render_block_core/button`) is the per-block form. Enqueuing a style, script or script module from this filter is supported. |
| `WP_HTML_Tag_Processor` | Since 6.2. `next_tag( $query )`, `set_attribute( $name, $value )`, `add_class()`, `get_updated_html()`. The way to change rendered block markup — including adding Interactivity API directives to core blocks. |
| Filter `block_type_metadata_settings` | `( array $settings, array $metadata )`. Adjust a `block.json`-registered block type's settings, e.g. `$settings['supports']['interactivity'] = true` for a core block. |
| Action `enqueue_block_assets` | Fires for the frontend **and** for the editor canvas. Styles enqueued here reach the editor iframe. |
| Action `enqueue_block_editor_assets` | Editor-only scripts (inspector controls, sidebar panels). |

## Core blocks added or changed

| Block | Notes |
|---|---|
| `core/breadcrumbs` | Since 7.0. Automatic trail. Filters below. |
| `core/icon` | Since 7.0. Attribute `icon` is a namespaced name. On 7.0 only the built-in `core/*` set exists (88 icons, e.g. `core/info`, `core/caution`, `core/check`, `core/star-filled`, `core/arrow-right`, `core/menu`, `core/cart`); an unknown name renders nothing. Custom icons need **7.1+**. |
| `core/navigation-overlay-close` | Since 7.0. Close button for a custom navigation overlay. It belongs inside the **overlay template part** — it is not an allowed child of `core/navigation`. |
| `core/heading` | 7.0 registers `h1`–`h6` inserter variations. The block name in markup is always `core/heading` with `"level": 1–6`. |
| `core/cover` | 7.0 adds `"backgroundType": "embed-video"` for YouTube/Vimeo backgrounds. |
| `core/gallery` | 7.0 lightbox: set `"linkTo": "lightbox"` on the gallery and `"lightbox": {"enabled": true}` on each inner `core/image`. The gallery itself has no `lightbox` attribute. |
| `core/accordion` | Since **6.9**. `core/accordion` → `core/accordion-item` → `core/accordion-heading` (holds `title`) + `core/accordion-panel`. |
| `core/details` | Since 6.3. Single native `<details>` disclosure. |
| `core/playlist`, `core/tabs` | **7.1+.** See `references/core-7.1-apis.md`. |

## Template part areas

Valid `area` values: `header`, `footer`, `uncategorized`, and (since 7.0) `navigation-overlay`.

## Breadcrumb filters (since 7.0)

| Filter | Notes |
|---|---|
| `block_core_breadcrumbs_items` | **One argument:** `array $breadcrumb_items`. Each item: `label` (string), `url` (string, optional), `allow_html` (bool, optional). With `allow_html` true the label goes through `wp_kses_post()`; otherwise `esc_html()`. Register with the default `add_filter( …, $callback )` — a callback that requires a second parameter throws `ArgumentCountError`. |
| `block_core_breadcrumbs_post_type_settings` | `( array $settings, string $post_type, int $post_id )`. Return `taxonomy` and/or `term` slugs to choose which taxonomy and term the trail uses. |

## Block Bindings

| Function / class | Notes |
|---|---|
| `register_block_bindings_source( $source_name, $source_properties )` | Since 6.5. Keys: `label`, `get_value_callback`, `uses_context`. |
| Built-in sources | `core/post-meta` (`args.key` = meta key), `core/post-data` (since 6.9; `args.field` = `date`, `modified` or `link`), `core/term-data` (since 6.9; `args.field` = `id`, `name`, `link`, `slug`, `description`, `parent`, `count`), `core/pattern-overrides`. |
| Filter `block_bindings_supported_attributes` | Since 6.9. `( string[] $supported_block_attributes, string $block_type )`. Lets a custom block expose attributes to bindings and, in 7.0, to Pattern Overrides. A per-block variant `block_bindings_supported_attributes_{$block_type}` also exists. |

## contentOnly editing (7.0)

| API | Notes |
|---|---|
| Default behaviour | Unsynced patterns (blocks carrying `metadata.patternName`) and template parts open in content-only mode. Editors unlock an unsynced pattern with **Edit pattern**; synced patterns and template parts with **Edit original**. |
| `disableContentOnlyForUnsyncedPatterns` | Editor setting. The only opt-out, and it is site-wide: set `$settings['disableContentOnlyForUnsyncedPatterns'] = true` in a `block_editor_settings_all` filter callback (code in `references/architecture.md → Content-Only Editing`). Template parts and synced patterns are unaffected by it. There is no per-pattern opt-out. |
| `"templateLock": "contentOnly"` | Block attribute. Forces content-only inside that block in every context, including the isolated editor. |
| `"role": "content"` on an attribute | In `block.json` / `attributes`. Marks the attribute as editable content. A custom block with no content-role attribute is hidden from List View inside content-only containers. |
| `"contentRole": true` in `supports` | Alternative when the block has no single content attribute. |
| `"listView": true` in `supports` | Adds a List View tab to the inspector for container blocks. |

## Block visibility

| Key | Notes |
|---|---|
| `metadata.blockVisibility: false` | Since 6.9. The block is not rendered at all. |
| `metadata.blockVisibility.viewport` | Since 7.0. An **object** keyed by `mobile`, `tablet`, `desktop` with boolean values; the block is hidden where the value is `false`: `{"viewport": {"mobile": false}}`. Hiding is CSS (`display: none` in a media query) — the markup stays in the DOM. An array (`["desktop"]`) is ignored. |
| Breakpoints | 7.0: mobile ≤ 480px, tablet ≤ 782px, desktop above — fixed. **7.1+** makes them configurable with `settings.viewport`. |
| Block support `visibility` | Defaults to `true`; set `"visibility": false` in a block's `supports` to opt the block out. |
| `theme.json` | 7.0 has **no** visibility setting — nothing needs enabling. **7.1+** adds `settings.blockVisibility.allowEditing` (set `false` to hide the controls). |

## theme.json keys (7.0)

| Key | Notes |
|---|---|
| `"version": 3` | Current schema version. No version 4. |
| `"$schema"` | `https://schemas.wp.org/wp/7.0/theme.json` (or `wp/7.1` when the site's minimum is 7.1). Never `trunk`. |
| `settings.typography.textIndent` | Since 7.0. `true` / `"subsequent"` (indent paragraphs that follow a paragraph) or `"all"`. Block support: `supports.typography.textIndent`. |
| `styles…typography.textIndent` | The indent amount (a CSS length). |
| `settings.typography.textColumns` / `styles…typography.textColumns` | Since 6.3; maps to CSS `column-count`. The Paragraph block supports it in 7.0. |
| `settings.dimensions.dimensionSizes` | Since 7.0. Presets `{ slug, name, size }`; CSS variable `--wp--preset--dimension--{slug}`. |
| `settings.dimensions.width` / `.height` | Since 7.0. Block supports `dimensions.width` / `dimensions.height`. |
| `styles.blocks.core/button` → `":hover"`, `":focus"`, `":focus-visible"`, `":active"` | Since 7.0. Only `core/button` in 7.0. |
| `settings.lightbox.enabled` | Enables the image lightbox by default (also settable per block under `settings.blocks.core/image`). |
| Block attribute `style.css` | Since 7.0. Per-block custom CSS (block support `customCSS`, on by default). |

## Abilities API

See `references/abilities-api.md`.

| Function / hook | Notes |
|---|---|
| `wp_register_ability( string $name, array $args ): ?WP_Ability` | Since 6.9. Required `$args`: `label`, `description`, `category`, `execute_callback`, `permission_callback`. Optional: `input_schema`, `output_schema`, `meta` (`annotations`, `show_in_rest`), `ability_class`. Name: `namespace/ability-name`, lowercase letters, digits, dashes. |
| `wp_register_ability_category( string $slug, array $args ): ?WP_Ability_Category` | Since 6.9. Slug: lowercase letters, digits and dashes **only — no slash**. `$args`: `label`, `description`. |
| `wp_unregister_ability()` / `wp_unregister_ability_category()` | Remove registrations. |
| `wp_has_ability()`, `wp_get_ability()`, `wp_get_abilities()` | Query the registry. `wp_get_ability( $name )->execute( $input )` runs an ability with its permission and schema checks. |
| `wp_has_ability_category()`, `wp_get_ability_category()`, `wp_get_ability_categories()` | Query categories. |
| Action `wp_abilities_api_categories_init` | Register categories here. |
| Action `wp_abilities_api_init` | Register abilities here (not on `init`). |
| REST | `wp-abilities/v1/abilities`, `wp-abilities/v1/abilities/{name}/run`, `wp-abilities/v1/categories`. Only abilities with `meta.show_in_rest => true` are exposed. |
| JS packages | `@wordpress/abilities` (`registerAbility`, `registerAbilityCategory`, `executeAbility`, `getAbilities`, `store`), `@wordpress/core-abilities` (loads server abilities). Since 7.0. |

## WP AI Client & Connectors (since 7.0)

See `references/ai-client.md`.

| Function / class / hook | Notes |
|---|---|
| `wp_ai_client_prompt( $prompt = null ): WP_AI_Client_Prompt_Builder` | Entry point. |
| `WP_AI_Client_Prompt_Builder` | Fluent builder; snake_case calls proxy to the PHP AI Client SDK. Full method list in `references/ai-client.md`. All methods are **instance** methods. |
| `->using_abilities( ...$abilities )` | Exposes registered abilities (names or `WP_Ability` objects) to the model as callable functions. |
| `WP_AI_Client_Ability_Function_Resolver` | Executes the function calls a model returns, limited to the abilities passed to its constructor. |
| `wp_supports_ai(): bool` | `false` when the constant `WP_AI_SUPPORT` is defined falsy or the `wp_supports_ai` filter returns false; otherwise `true`. An off-switch, not a capability check. |
| Filter `wp_ai_client_prevent_prompt` | `( bool $prevent, WP_AI_Client_Prompt_Builder $builder )`. Return `true` to block a prompt; the generating call returns `WP_Error` with code `prompt_prevented`. |
| Filter `wp_ai_client_default_request_timeout` | Default HTTP timeout in seconds (`float`, default 30). |
| Action `wp_connectors_init` | Receives `WP_Connector_Registry`. For overriding connector metadata or registering non-AI connectors. |
| `WP_Connector_Registry` | `register( string $id, array $args ): ?array`, `unregister( string $id ): ?array`, `is_registered( string $id ): bool`. Use only inside `wp_connectors_init`. |
| `wp_get_connector( $id )`, `wp_get_connectors()`, `wp_is_connector_registered( $id )` | Public read functions; available after `init`. |
| `WordPress\AiClient\AiClient::defaultRegistry()` | The **provider** registry. Provider plugins call `->registerProvider( $class_name )` on it; their connector is then created automatically. |

## Script Modules & Interactivity API

| Function / API | Notes |
|---|---|
| `wp_register_script_module( string $id, string $src, array $deps = array(), $version = false, array $args = array() )` | Since 6.5. |
| `wp_enqueue_script_module( string $id )` | Since 6.5. May be called while a block renders. |
| `script_module_data_{$module_id}` filter | Since 6.7. Passes data from PHP to a script module: WordPress prints the returned array as JSON in a `<script id="wp-script-module-data-{$module_id}">` element. `wp_localize_script()` does not work for script modules. |
| `wp_deregister_script_module()` / `wp_dequeue_script_module()` | Since 6.5. |
| `wp_set_script_module_translations( string $id, string $domain = 'default', string $path = '' ): bool` | Since 7.0. |
| `load_script_module_textdomain( string $id, string $domain = 'default', string $path = '' )` | Since 7.0. Returns `string|false`. |
| `wp_interactivity_state( ?string $namespace, array $state )` | Since 6.5. Server-side initial state for a store. |
| `wp_interactivity_data_wp_context( array $context, string $namespace = '' ): string` | Since 6.5. Returns a ready `data-wp-context='…'` attribute. |
| `wp_interactivity_process_directives( string $html ): string` | Since 6.5. Core calls it for blocks with `supports.interactivity`. |
| JS `@wordpress/interactivity` | `store`, `getContext`, `getElement`, `getConfig`, `getServerState`, `getServerContext`, `withScope`, `withSyncEvent`, `splitTask`, and (since 7.0) `watch`. |
| Directives | `data-wp-interactive`, `data-wp-context`, `data-wp-on--{event}`, `data-wp-on-window--{event}`, `data-wp-on-document--{event}`, `data-wp-bind--{attr}`, `data-wp-class--{name}`, `data-wp-style--{prop}`, `data-wp-text`, `data-wp-init`, `data-wp-run`, `data-wp-watch` (all since 6.5). |
| `core/router` store `state.url` | Populated on the server since 7.0. |

## Block Hooks

| Function / filter | Notes |
|---|---|
| Filter `hooked_block_types` | Since 6.4. `( string[] $hooked_block_types, string $relative_position, string $anchor_block_type, $context )`. Position is `before`, `after`, `first_child` or `last_child`. The anchor is a **block type** such as `core/post-content` — never a pattern slug. |
| Filter `hooked_block_{$hooked_block_type}` | Since 6.5. Adjust the inserted block's attributes. |
| `blockHooks` in `block.json` / `block_hooks` arg | Declarative form. |
| Filter `rest_block_hooks_post_types` | Since 7.0. `( array $post_types, string $post_type, $post )`. Default `post`, `page`, `wp_block`, `wp_navigation`. Add a custom post type here so its hooked blocks appear in the editor. |
| `resolve_pattern_blocks( $blocks )` | Since 6.6. Expands `core/pattern` references in a parsed block tree. |

## Performance

| API | Notes |
|---|---|
| Filter `wp_speculation_rules_configuration` | Since 6.8. Core prints speculation rules itself. Return `array( 'mode' => 'prefetch'|'prerender'|'auto', 'eagerness' => 'conservative'|'moderate'|'eager'|'auto' )` or `null` to disable. Off for logged-in users and plain permalinks. |
| Filter `wp_speculation_rules_href_exclude_paths` | Since 6.8. `( string[] $paths, string $mode )`. |
| `wp_get_loading_optimization_attributes( $tag_name, $attr, $context )` | Since 6.3. Returns `loading` / `decoding` / `fetchpriority` for a media tag. |

## Fonts

| API | Notes |
|---|---|
| `theme.json` `settings.typography.fontFamilies` | Version-controlled fonts shipped with the theme. |
| Font Library | Since 6.5; since 7.0 a screen for every theme type at **Appearance → Fonts**. Uploaded fonts are stored in `wp-content/uploads/fonts`. |
| `wp_register_font_collection( string $slug, array $args )` / `wp_unregister_font_collection( string $slug )` | Since 6.5. Register a collection of installable fonts. |

## Admin, users, security

| API | Notes |
|---|---|
| `wp_enqueue_view_transitions_admin_css(): void` | Since 7.0. |
| `wp_admin_bar_command_palette_menu( WP_Admin_Bar $wp_admin_bar ): void` | Since 7.0. Core callback; do not call directly. |
| Filter `default_role_dropdown_excluded_roles` | Since 7.0. Roles hidden from Settings → General → New User Default Role. Default `array( 'administrator', 'editor' )`. |
| Filters `wp_hash_password_algorithm`, `wp_hash_password_options` | Since 6.8. |
| `wp_get_image_alttext( $file )` | Since 7.0. Takes an image **file path** and returns alt text embedded in the file's metadata. Defined in `wp-admin/includes/image.php` (admin context only). For an attachment's stored alt text use `get_post_meta( $id, '_wp_attachment_image_alt', true )`. |

## Localisation & escaping

`__()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()`, `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`, `wp_json_encode()`.

## Calling convention

Call core functions with **positional** arguments. WordPress states that named arguments on core functions are not supported; a parameter rename in a later release turns `wp_register_style( handle: … )` into a fatal error.

## Names that look real but are not (do not use)

| Wrong | Reality |
|---|---|
| `script_module_handle` on `register_block_style()` | No such key. Bind a module to a block with `view_script_module_ids` on `register_block_type()`; load pattern scripts with the pattern-asset loader in `references/architecture.md`. |
| `wp_is_collaboration_enabled()`, `wp_is_collaboration_allowed()`, `WP_ALLOW_COLLABORATION`, `WP_HTTP_Polling_Sync_Server`, `WP_Sync_Post_Meta_Storage` | Real-time collaboration was removed before 7.0 shipped and is not enabled in 7.1. None of these exist. |
| `WP_AI_Client_Prompt_Builder::is_supported_for_text_generation()` (static call) | Instance method: `wp_ai_client_prompt( … )->is_supported_for_text_generation()`. |
| `$result->getContent()`, `$usage->input` | `GenerativeAiResult` uses `toText()` / `toFile()`; `TokenUsage` uses `getPromptTokens()` / `getCompletionTokens()` / `getTotalTokens()`. |
| `$builder->set_model()`, `$builder->with_options()` | Not builder methods. Use `using_model_preference()` and the `using_*` / `as_*` methods. |
| `WP_Error` code `no_active_connector` | Not a core error code. |
| `wp_register_block_template_part()` | Does not exist. Template parts are files in `parts/` listed under `templateParts` in `theme.json`. |
| `register_block_ability()` | `wp_register_ability()`. |
| Ability category slug with a slash (`my-theme/editorial`) | Rejected. Use `my-theme-editorial`. |
| `'is_ai_ready' => true` on `register_block_template()` | Not a parameter. |
| Binding sources `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-author-name`, `core/site-title`, `core/site-tagline` | Not registered. Use the sources listed under Block Bindings, or a custom source. |
| `"__experimentalSettings": {"disableContentOnlyForUnsyncedPatterns": true}` | `__experimentalSettings` is an unrelated block support flag. There is no per-pattern content-only opt-out. |
| JS filter `blockEditor.settings` | Does not exist. Use the PHP `block_editor_settings_all` filter. |
| `__experimentalRole: 'content'` | Deprecated since 6.7. Use `"role": "content"`. |
| `"viewport": ["tablet", "desktop"]` | Wrong shape; ignored. Use the object form under Block visibility. |
| `settings.blockVisibility.viewport` in `theme.json` | Not a key in any version. |
| `"lightbox": true` on `core/gallery` | Not an attribute. See Core blocks. |
| `wp:navigation-overlay-close` inside `wp:navigation` | Not an allowed child. Put it in the overlay template part. |
| `WP_Icons_Registry::get_instance()->register()` / `wp_register_icons` filter / `register_icon()` | 7.0 has no public icon registration. 7.1+ uses `wp_register_icon_collection()` and `wp_register_icon()`. |
| Filter `block_editor_rest_api_get_items_query_params` | Does not exist. |
| `dataviews.json`, a `dataviews` key in `theme.json`, `border.blur` | Not part of the `theme.json` schema. |
| `"version": 4` in `theme.json` | No such version. |
| `https://schemas.wp.org/trunk/theme.json` | Tracks the development branch. Pin a release. |
| `@wordpress/viewport-visibility` | Not a package. |
| `wp_register_font_library_font()` | Does not exist. |
| `currentUserCan` from `@wordpress/core-data` | Not an export. Use the `canUser` selector of the `core` store. |
| `wp_collaboration_inject_setting()`, `can_user_sync_entity_type()` | Do not exist. |
| `apply_block_hooks_to_content_from_post_object()`, `insert_hooked_blocks_into_rest_response()`, `wp_maybe_add_fetchpriority_high_attr()`, `_wp_connectors_*()` | Real but private. Use `rest_block_hooks_post_types`, `wp_get_loading_optimization_attributes()` and the public connector functions. |
| `wp_generate_uuid4()` as a 7.0 feature | Exists since 4.7. |
