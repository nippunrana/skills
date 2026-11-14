---
name: wp-block-theme
description: Expert WordPress 7 theme and plugin developer. Use for Full Site Editing (FSE) block themes, templates, block patterns, theme.json, and WordPress 7 APIs (WP AI Client, Abilities, DataViews, Block Bindings/Hooks). Also covers WooCommerce block themes and checkout extensibility.
---

# WordPress 7 Theme & Plugin Developer
*Target: WordPress 7.0 and later. The WordPress core APIs named in this file and in the theme, AI, Abilities, DataViews and core-API references were checked against core source at 7.0.8 and 7.1.4; the WooCommerce references against WooCommerce 11.3.0. Earlier versions are out of scope. Features that exist only from 7.1 are marked **7.1+** and collected in `references/core-7.1-apis.md`.*

## Required Context (Dynamic Variables)

Before executing this skill, resolve the following variables. The preferred source is the project's
context file in the project root: `AI_CONTEXT.md` (parent theme) or `AI_CONTEXT-Child.md` (child
theme), otherwise `ai-context.md` or `AGENTS.md` — read whichever is present. **If none exists**,
derive the values from context instead (theme directory name, inline information in the user's
request, or ask the user directly). Never leave a `{{placeholder}}` literal in generated output.

- `{{THEME_SLUG}}`: The kebab-case slug of the active theme (e.g., `my-theme`).
- `{{THEME_PREFIX}}`: The same slug in snake_case (e.g., `my_theme`). Use it wherever PHP needs an identifier — function names, callbacks. A kebab-case slug is not a valid PHP function name.
- `{{THEME_NAME}}`: The human-readable name of the theme.
- `{{TEXT_DOMAIN}}`: The text domain used for localization.
- `{{PARENT_THEME_SLUG}}`: (Optional) The slug of the parent theme if working in a child theme.

Also establish two facts about the target site before writing code — they are not placeholders, but they decide what you may write:

- **WordPress version** (context file, `wp-includes/version.php`, or ask). If unknown, assume 7.0 and use nothing marked 7.1+.
- **PHP version** (context file, `php -v` or Tools → Site Health → Info → Server, `composer.json`, `.wp-env.json`, or ask). The default target is **PHP 8.4**. If the site runs 8.2 or 8.3, load `references/php-compatibility.md`. If the version cannot be established, do **not** assume 8.4 — write the 8.2-compatible form and say so.

**Record both versions.** If the project has a context file, add or update `PHP version:` and `WordPress version:` lines in it so the next session starts from facts. If it has none, report the versions and offer to record them — do not create the file unasked.

All examples below use these placeholders. Replace them with actual values from the project context.

A skill for creating and extending WordPress Full Site Editing (FSE) block themes — templates,
template parts, block patterns, theme.json design systems, and modular asset pipelines.

The most common entry point is **converting an existing HTML/CSS/JS design into a block theme
template**. This is the primary workflow covered in Section 0. All other sections cover the
underlying building blocks used during that process.

## How to approach a request

1. **Identify the deliverable** — Is this an HTML→template conversion, a new template from
   scratch, a pattern, a template part, a theme.json change, an asset pipeline fix, **or a plugin
   feature** (an Ability, a WP AI Client call, a Connector, a DataViews admin screen, a
   PHP-only block via `autoRegister`)? Theme work routes through Sections 0–6; plugin work routes
   through Section 7 and the dedicated reference files (`ai-client.md`, `abilities-api.md`,
   `dataviews.md`).
2. **Determine Target Theme & Read Context** — Identify if you are developing the **Core (Parent Theme)** or an **Extension (Child Theme)**. 
   - Read `AI_CONTEXT-Child.md` if working on a child theme.
   - Read `AI_CONTEXT.md` if working on the parent theme core.
   - Always prioritize child themes for project-specific features unless you are building foundational capabilities into the parent.
3. **Follow the architecture rules** in `references/architecture.md`.
4. **Pre-write gates.** Before writing any code, all five gates must pass. If any gate fails, fix it before proceeding:
   - **API gate:** Every WordPress function, class, hook, block name and `theme.json` key you use must be one you know exists. `references/api-allowlist.md` is the checked list for everything specific to WordPress 6.5–7.1; long-established basics (`add_action()`, `is_admin()`, `esc_url_raw()`, `core/group`, `customTemplates`) need no entry. For anything in between — a name you are not certain of — confirm it in core source or on developer.wordpress.org before writing it. The allowlist's last section, "Names that look real but are not", lists names to never use — among them `script_module_handle`, `wp_is_collaboration_enabled()`, a static `::is_supported_for_text_generation()` call, `register_block_ability()`, `__experimentalRole`, an ability category slug containing a slash, and `"version": 4`. Call core functions with positional arguments only.
   - **PHP gate:** Code is written for the site's established PHP version — 8.4 by default, never newer syntax than the site runs (`references/php-compatibility.md` for 8.2 and 8.3). Every function and callback has typed parameters and a return type; nullable parameters are written `?Type $x = null`. Hook callbacks (`add_action` / `add_filter`) are named functions passed by name, never closures. Use `??`, `str_contains()`, `match`, and `?->` where appropriate, and PHP 8.4 features where they fit. PHP identifiers use `{{THEME_PREFIX}}`, never the kebab-case slug. Full checklist: `references/architecture.md → PHP Requirements`. For WordPress PHP house style (Yoda conditions, `array()` syntax, tabs, naming, `$wpdb->prepare()`, prohibited constructs), load `references/php-coding-standards.md` before writing. `declare(strict_types=1);` requirement by file type:

     | File path pattern | `declare(strict_types=1);` required? |
     |---|---|
     | `functions.php`, `inc/**/*.php`, `src/**/*.php` | Yes |
     | `patterns/*.php` (docblock + block markup only, no function/class) | No — strict_types has no effect without typed signatures |
     | `patterns/*.php` (contains any `function` or `class` keyword) | Yes — but move the function out; that's a smell |
     | Backing PHP for template parts (`patterns/header-*.php`, etc.) | No (same rule as patterns) |
   - **theme.json gate:** `"version": 3` in all `theme.json` files. Never write `"version": 4`. `$schema` is pinned to a release (`https://schemas.wp.org/wp/7.0/theme.json`), never `trunk`.
   - **WordPress version gate:** Every feature used must exist on the site's WordPress version. Anything marked **7.1+** (custom icons, `core/playlist`, `core/tabs`, responsive `@mobile` / `@tablet` styles, `settings.viewport`) is only for a site known to run 7.1 or later — see `references/core-7.1-apis.md`. Unknown version means 7.0 rules.
   - **Namespace gate:** Any call to `register_block_template()` MUST use the double-slash namespace: `{{THEME_SLUG}}//template_name` (lowercase). A single slash is rejected (`_doing_it_wrong()` plus a `WP_Error`) and the template is never registered.
5. **Execute** using the implementation checklists below. Use `references/scaffold-tree.md` for the directory layout.
6. **Verify** — run a quick filesystem audit (every sub-pattern slug has its file, its folder, its registry entry and its `is-style-` class). If the user reports that file changes are not showing, explain the Site Editor **Reset** action and that the template's revisions can restore their earlier edits first.

---

## Core Architectural Rules

- **Respect the Parent/Child boundary.**
  - Child Theme Context: if `AI_CONTEXT-Child.md` exists and is the active focus, never touch the parent theme directory. All modifications go in the child theme.
  - Parent Theme Context: if you are explicitly developing the parent core (active folder matches the theme folder and task is foundational), you may modify parent files. If a feature is project-specific, suggest moving it to a child theme instead.
- **Native-first conversion.** Recreate designs using Core Blocks (`core/group`, `core/columns`, `core/heading`, `core/image`, `core/icon`, etc.). Use the Grid layout (`{"layout": {"type": "grid"}}` on `core/group`) for grid layouts. This keeps colors, typography, and spacing under Site Editor control. **NEVER use `wp:html`. NEVER paste raw HTML into a template file.** When a design cannot be expressed in core blocks, use this fallback hierarchy:
  1. Compose with `core/group`, `core/columns`, and the Grid layout. *(Default.)*
  2. Register a **PHP-only block**: `register_block_type()` with `'supports' => array( 'autoRegister' => true )` and a `render_callback`. No `block.json`, no JS, no build step. WP 7.0 auto-generates Inspector Controls for string, number, integer, and boolean attributes. **Constraint:** Attributes must be stored in the block's JSON boundary; "sourced" attributes (e.g., from HTML) are not supported.
  3. **Classic dynamic block** (`register_block_type()` with full `block.json` + `render_callback`) — only when the block needs custom JS-driven editor controls beyond auto-generated ones.
  See `references/architecture.md → Native-First Decision Algorithm` for the full decision tree.
- **Template registration — use exactly one mechanism per template name:**
  - Use `theme.json` `customTemplates` if the template ships inside a theme (parent or child).
  - Use `register_block_template()` if the template ships inside a plugin or must be reused across themes.
  - **NEVER use both for the same template name.**
  See `references/architecture.md → Template Registration API` for the `register_block_template()` rules.
- **Pattern types at a glance — the single most important decision before writing any pattern file:**

  | Pattern type | Outermost block? | `lock`? | `className`? | `Inserter`? |
  |---|---|---|---|---|
  | **Master / assembler** (`Inserter: false`) | ❌ No wrapper block | ❌ No | ❌ No | `false` |
  | **Sub-pattern** (standalone section) | ✅ Yes | ✅ `{move:true, remove:true}` | ✅ `is-style-{slug}` | `true` (or `false` if internal-only) |
  | **Template-part backing pattern** | ✅ Yes | ✅ `{move:true, remove:true}` | ✅ `is-style-{slug}` | `false` |

  Master patterns MUST contain only flat `<!-- wp:pattern -->` calls — no wrapper, no lock, no CSS. All layout and CSS live in sub-patterns.

- **Two independent protections — do not confuse them:**
  - **Structural lock** (`lock` attribute): stops editors moving or deleting the block. You write this. Apply to sub-patterns and template-part backing patterns; omit on master patterns.
  - **Content-only editing**: stops editors restructuring *inner* content. WordPress 7.0 applies this by default; you normally write nothing.

- **Content-only editing — what WordPress 7.0 does, and what to write:**

  | Context | 7.0 default | What to write |
  |---|---|---|
  | Unsynced pattern (every pattern a theme ships) | Content-only. Editors unlock with **Edit pattern**. | Nothing. |
  | Template part | Content-only inside a template or page. Editors unlock with **Edit original**. | Nothing. |
  | A block whose inner structure must stay locked everywhere | — | `"templateLock": "contentOnly"` on that block. |
  | Custom block used inside any of the above | Hidden from List View unless it declares content. | `"role": "content"` on each editable attribute (or `"contentRole": true` in `supports`). |

  The only opt-out is site-wide: the `disableContentOnlyForUnsyncedPatterns` editor setting through `block_editor_settings_all`. **There is no per-pattern opt-out** — never emit an `__experimentalSettings` attribute. See `references/architecture.md → Content-Only Editing`.
- **Block locking — three non-overlapping rules** (structural lock only; see table above):
  - **Sub-patterns and inserter-visible patterns:** outermost block MUST carry `"lock": {"move": true, "remove": true}` AND `"className": "is-style-{pattern-slug}"`.
  - **Master/assembler patterns** (`Inserter: false`, called only via `wp:pattern`): MUST contain only a flat list of `<!-- wp:pattern -->` comments. No wrapper block. No lock attribute. No styles. No `className`.
  - **Template parts** (outermost block of the backing PHP pattern): MUST carry `"lock": {"move": true, "remove": true}` AND `"className": "is-style-{pattern-slug}"`.
- **CSS scoping via Block Style Variations.** Scope CSS strictly to the block's variation class (e.g. `.is-style-hero-section`). Do not enqueue pattern CSS yourself and do not pass `style_handle` to `register_block_style()` — in a block theme that stylesheet reaches the editor but never the frontend. Instead add the slug to the **pattern-asset registry** (`inc/pattern-assets.php`): it registers the variation, enqueues the CSS only where a block with that class renders, and loads it into the editor canvas. Full file: `references/architecture.md → Asset Pipeline`. All hand-authored CSS must follow `references/coding-standards.md` (tabs, logical property order, value formatting).
- **JavaScript — two lanes:**
  - **Reactive** (state, user-driven UI, Interactivity API). `data-wp-*` directives cannot be typed into a core block in a pattern file. Two routes, in order of preference: **(A)** the element is a core block already in the pattern → add the directives at render time with `WP_HTML_Tag_Processor`; the store is a script module at `patterns/{sub-pattern}/view.js`, loaded by `'module' => true` in the pattern-asset registry. **(B)** the markup is custom → a PHP-only dynamic block prints it, with its store at `inc/blocks/{block}/view.js` bound through `view_script_module_ids`. `register_block_style()` has no script key. See `references/architecture.md → Script Modules: loading Interactivity code`.
  - **Non-reactive** (IntersectionObserver, scroll or GSAP animation, no shared state): a classic script at `patterns/{sub-pattern}/index.js`, loaded per variation by setting `'script' => true` in the pattern-asset registry. Wrap it in a `DOMContentLoaded` listener, scope every selector to the variation class, and make the listener's first statement `if ( window.frameElement ) { return; }`.
  - Never put reactive state in an `index.js`. All JS follows `references/js-coding-standards.md`.
- **Block Hooks** insert a block before, after, or inside every instance of an anchor **block type** (never a pattern slug). In the editor they apply to `post`, `page`, `wp_block` and `wp_navigation`; add a custom post type with the `rest_block_hooks_post_types` filter (WP 7.0). See `references/architecture.md → Block Hooks`.
- **Viewport block visibility.** `metadata.blockVisibility.viewport` is an **object**: `{"mobile": false}` hides the block on mobile. Keys are `mobile`, `tablet`, `desktop`; `false` means hidden. Hiding is CSS-based — the block stays in the DOM, so never use it for access control. Nothing needs enabling in `theme.json` on 7.0.
- **Font Library.** Since 7.0 every theme type has a font screen at Appearance → Fonts. Use `theme.json` `fontFamilies` for fonts the theme ships. When a user says "install a font", direct them to Appearance → Fonts. Do not enqueue a font that `theme.json` or the Font Library already provides.
- **Synced patterns are not something a theme can ship.** Every `patterns/*.php` file is an unsynced pattern. A synced pattern (and its Pattern Overrides) is created by a user in the Site Editor and stored in the database. When a user wants one shared, overridable section, give them the pattern file plus the editor steps — never promise override behaviour from the file alone. Custom blocks opt attributes into overrides with the `block_bindings_supported_attributes` filter.
- **Block Bindings API** wires dynamic data into Core Blocks. Built-in sources are `core/post-meta`, `core/post-data`, `core/term-data` and `core/pattern-overrides` — nothing else. See `references/architecture.md → Block Bindings API`.
- **Template Parts for global areas** (Headers, Footers). Register them in `theme.json` under `templateParts`. If a part needs PHP (image URLs, dynamic content), make the `.html` file a thin pointer that calls a PHP pattern.
- **System vs. functional patterns.** Use `Inserter: false` for assembler patterns and template-part backing patterns — anything that exists only to power a template. Patterns editors should insert themselves keep `Inserter: true` (the default).
- **theme.json token inheritance in child themes.** Arrays like `palette` overwrite the parent rather than merge. A child that defines `palette` must repeat every parent color it still needs, then append its own. `defaultPalette` is unrelated — it only toggles WordPress's built-in colors.
- **Fluid Typography and Spacing.** Enable `typography.fluid: true` and define spacing tokens with `clamp()` so designs scale without manual media queries.
- **Slugs, prefixes and text domains.** Prefix pattern slugs and categories with the current theme's directory name (e.g., `my-theme/` for parent, `my-theme-child/` for child). Prefix PHP functions with the snake_case form (`my_theme_`). Use the corresponding text domain for localization.
- **Push layout to theme.json.** Padding, margin, and gap belong in `theme.json` Section Styles (variations under `styles.blocks.{block}.variations.{name}`). Reserve `style.css` for things JSON cannot express (gradients, complex transforms, scoped overrides).

---

## 0. Converting HTML/CSS/JS into a Block Theme Template

This is the primary workflow when the user provides a finished design (HTML export, static page,
Figma handoff, etc.) and wants it running inside WordPress FSE.

WordPress's block editor strips any raw HTML it doesn't recognise as a registered block. A
static HTML file dropped straight into a template file will be silently gutted. The conversion
process wraps the HTML correctly, scopes the CSS so it doesn't pollute the Site Editor, and
wires up asset loading so everything works on the frontend AND inside the editor iframe.

**Read `references/html-conversion.md` now** — it contains the block mapping, the conversion rules
and a complete worked example (input HTML → all output files). Use it as your primary guide.

### Quick-reference checklist

Before handing off, verify every item:

#### Structural (hard requirements)
- [ ] Agreed on a kebab-case **slug** with the user
- [ ] `templates/{slug}.html` exists — opens with `wp:template-part` header, calls master pattern + `wp:post-content`, closes with `wp:template-part` footer (decide which header/footer variant to use per-template)
- [ ] `patterns/{slug}.php` (master) exists with `Inserter: false` and contains only flat `<!-- wp:pattern -->` calls — no wrapper block, no lock, no CSS
- [ ] All logical sections have sub-pattern files in `patterns/`
- [ ] Design mapped to Core Blocks (Group, Columns, etc.); fallback to PHP-only block (`autoRegister: true`) before classic dynamic block; NEVER use `wp:html`

#### CSS scoping (conventions)
- [ ] CSS + HTML markup follows `references/coding-standards.md` (tabs not spaces, logical property order, quoted attributes, correct value formatting)
- [ ] Layout CSS is strictly scoped to the block style variation class (e.g. `.is-style-hero-section`), avoiding a catch-all page wrapper to prevent style bleed into independent sub-patterns
- [ ] Modular patterns (CTA, Header, FAQ, etc.) use their own separate CSS class (e.g. `.is-style-main-cta`) and are not placed inside a page-specific wrapper that alters their layout
- [ ] Master patterns (assemblers) have no layout CSS — all styles live in sub-patterns
- [ ] Sub-pattern CSS saved to its own directory (e.g., `patterns/faq-section/style.css`)
- [ ] Every sub-pattern's outermost block has `"lock": {"move": true, "remove": true}` AND a unique variation class (e.g. `is-style-{pattern-slug}`)
- [ ] Non-reactive scripts (animations, observers) saved to `patterns/{sub-pattern}/index.js` with a `DOMContentLoaded` guard gated on `window.frameElement` and scoped selectors, and `'script' => true` set in the registry; reactive stores saved to `patterns/{sub-pattern}/view.js` with `'module' => true` (directives added at render) or to a PHP-only dynamic block; JS style follows `references/js-coding-standards.md`
- [ ] Image paths use `get_theme_file_uri( 'assets/…' )` — no hardcoded URLs

#### Configuration (hard requirements)
- [ ] `theme.json` has the `customTemplates` entry
- [ ] `theme.json` uses `"version": 3` and `"$schema": "https://schemas.wp.org/wp/7.0/theme.json"`
- [ ] `style.css` header declares `Requires at least:` and `Requires PHP:` matching the versions the code was written for
- [ ] Every sub-pattern slug has an entry in the pattern-asset registry (`inc/pattern-assets.php`), and `functions.php` requires that file
- [ ] No `style_handle`, no `script_module_handle`, no direct `wp_enqueue_style()` for pattern CSS

### Common conversion pitfalls

| Problem | Symptom | Fix |
|---|---|---|
| "Black box" templates | Can't edit colors/text in the Site Editor | Rebuild custom sections using Core Blocks; only fall back to a dynamic block (`register_block_type()` + `render_callback`) when the design truly cannot map to core. |
| Raw HTML stripped | Content disappears in editor | Wrap in a dynamic block registered via `register_block_type()`; do not paste raw HTML into a template. |
| CSS bleeds into editor UI | Styles affect menus/toolbars | Scope rules strictly to `.is-style-{slug}` variation class |
| Template not accessible | A11y warnings/fail | Use semantic tags (nav, main) and ARIA labels |
| Client breaks layout | Sections deleted accidentally | Apply `"lock": {"move": true, "remove": true}` to structural blocks |
| Editor doesn't match frontend | WYSIWYG mismatch | The slug is missing from the pattern-asset registry, so the loader's `enqueue_block_assets` hook never loads it into the editor |
| Animations fire in a preview iframe | Jarring experience | Gate the script on `window.frameElement` |
| theme.json colors missing | Parent palette deleted | Child `theme.json` must include parent palette entries before appending |

---

## 1. Creating a New Custom Page Template (from scratch)

### Files to create / modify

| File | Action |
|---|---|
| `templates/{slug}.html` | New — the template entry point |
| `patterns/{slug}.php` | New — the main pattern (assembler) |
| `theme.json` | Modify — add entry under `customTemplates` |
| `inc/pattern-assets.php` | Modify — add one registry entry per sub-pattern |

### Step-by-step checklist

- [ ] **Determine the slug** — kebab-case, e.g. `my-landing-page`.
- [ ] **Create `templates/{slug}.html`**: Choose the correct architecture before writing a single line.

  **OPTION A — Site Editor–managed layout** (pattern hardcoded in template):
  Use when the layout is structural chrome that editors should never accidentally delete. The pattern is locked to this template. Content creators edit text/images *inside* the pattern via the Site Editor — they cannot restructure it from the Page Editor.
  ```html
  <!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
  <!-- wp:pattern {"slug":"{{THEME_SLUG}}/{slug}"} /-->
  <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
  <!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
  ```

  **OPTION B — Page Editor–editable layout** (no pattern in template; set `Inserter: true` on the pattern):
  Use when editors must freely add, remove, or reorder sections in the normal Page Editor. The pattern goes directly into page content, not the template. Editors insert it from the Block Inserter.
  ```html
  <!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
  <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
  <!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
  ```

  **Decision rule:** if the answer to "should an editor ever restructure this from the Page Editor?" is yes → Option B. Otherwise → Option A.
- [ ] **Create `patterns/{slug}.php`** — see pattern header format below.
- [ ] **Create each sub-pattern's `patterns/{sub-pattern}/style.css`** — every registry entry needs one — and, only where needed, `index.js` or `view.js`
- [ ] **Register in `theme.json`** under `customTemplates`:
  ```json
  { "name": "{slug}", "title": "Human-Readable Title", "postTypes": ["page"] }
  ```
- [ ] **Register assets** — add each sub-pattern slug to the pattern-asset registry in `inc/pattern-assets.php` (`references/architecture.md → Asset Pipeline`). For JS, apply this two-lane decision:

  | JS lane | When | File | How it loads |
  |---|---|---|---|
  | Reactive (state, directives, user-driven UI) | Shared state or Interactivity API directives | `patterns/{slug}/view.js` (core blocks) or `inc/blocks/{block}/view.js` (custom markup) | Core blocks: `'module' => true` in the registry, directives added at render with `WP_HTML_Tag_Processor`. Custom markup: PHP-only dynamic block with `view_script_module_ids` |
  | Non-reactive (IntersectionObserver, GSAP, scroll animations) | Read-only DOM effects, no shared state | `patterns/{slug}/index.js` | `'script' => true` in the registry; the listener's first statement is `if ( window.frameElement ) { return; }` |

  > JS style for both lanes (tabs, single quotes, spaces inside parens/brackets): see `references/js-coding-standards.md`.

### Pattern PHP header format

```php
<?php
/**
 * Title: My Landing Page
 * Slug: {{THEME_SLUG}}/{slug}
 * Categories: {{THEME_SLUG}}
 * Keywords: landing, page, {keywords}
 * Inserter: false
 */
?>
<!-- No global wrapper. Master patterns only assemble sub-patterns to prevent CSS bleed. -->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/hero-section"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/another-section"} /-->
```

See `references/architecture.md → Asset Pipeline` for the full `inc/pattern-assets.php` and `functions.php`.

---

## 2. Creating a Template Part (Header / Footer / etc.)

Template parts live in `parts/` and must be registered in `theme.json` under `templateParts`.

### Single-file vs. Two-file Architecture

| Template part needs… | Architecture | Files |
|---|---|---|
| Static markup only (no PHP, no theme-relative image paths) | Single-file | `parts/{name}.html` only |
| PHP (image URLs via `get_theme_file_uri()`, dynamic content, icon helpers) | Two-file: thin `.html` pointer → PHP pattern | `parts/{name}.html` + `patterns/{name}.php` |

Most real-world template parts need PHP for image paths or dynamic content — default to two-file unless the part is truly static markup.

### Files

| File | Action |
|---|---|
| `parts/{name}.html` | New — the part markup (or pointer to PHP pattern) |
| `patterns/{name}.php` | New — **only if PHP is needed** (image URLs, dynamic content) |
| `patterns/{name}/style.css` | New — scoped CSS for this component |
| `patterns/{name}/index.js` | New (if non-reactive JS needed) |
| `theme.json` | Modify — add to `templateParts` |
| `inc/pattern-assets.php` | Modify — add the part's slug to the registry |

### The Correct Two-File Architecture

For header/footer components that need PHP (image paths, icon helpers, etc.), use a **two-file
approach**: a thin `.html` template part that delegates to a PHP pattern.

**`parts/header-top-bar-variant-a.html`** — just a pointer:
```html
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/header-top-bar-variant-a"} /-->
```

**`patterns/header-top-bar-variant-a.php`** — the real content with PHP:
```php
<?php
/**
 * Title: Header Top Bar Variant A
 * Slug: {{THEME_SLUG}}/header-top-bar-variant-a
 * Categories: {{THEME_SLUG}}
 * Inserter: false
 */
?>
<!-- wp:group {"className":"is-style-header-top-bar-variant-a","lock":{"move":true,"remove":true}} -->
<div class="wp-block-group is-style-header-top-bar-variant-a">
	<!-- wp:icon {"icon":"core/caution"} /-->

	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'FOR LABORATORY RESEARCH USE ONLY.', '{{TEXT_DOMAIN}}' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

> **Why this works:** WordPress resolves `wp:pattern` through its block pattern registry, which
> executes the PHP file. The `.html` template part itself has no PHP, but the pattern it calls does.

> 💡 **System Patterns & `Inserter: false`:** Always add `Inserter: false` to patterns that act
> as backend logic for template parts or master templates. This keeps the user's UI clean.
> WordPress perfectly renders these hidden patterns whenever they are called via `wp:pattern`
> inside your `.html` template parts.

### Placing Template Parts in the Root `.html` Template

Always call template parts directly in the root `templates/{slug}.html` file — never nest them inside a master pattern:

```html
<!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/{slug}"} /-->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
<!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
```

This ensures true modularity and lets the developer choose which header/footer variant to use per-template.

### Asset Enqueuing for Template Parts

Because the template part relies on a backing PHP pattern, its CSS loads exactly like any other pattern's: give the outermost block the `is-style-{slug}` class and add `{slug}` to the pattern-asset registry.

### theme.json registration

```json
"templateParts": [
  {
    "name": "header-top-bar-variant-a",
    "title": "Header Top Bar Variant A",
    "area": "header"
  }
]
```

Valid `area` values: `header`, `footer`, `uncategorized`, `navigation-overlay` (WP 7.0 — for mobile menu overlays).

**Navigation Overlay setup (WP 7.0):** To register a custom mobile overlay:
1. Add `{"area": "navigation-overlay", "name": "mobile-menu", "title": "Mobile Menu"}` to `templateParts` in `theme.json`.
2. Create `parts/mobile-menu.html` — **always include `core/navigation-overlay-close` in this part**, otherwise WordPress adds a fallback close button that will not match the design. The close block is never placed inside `core/navigation`.
3. Point the Navigation block at the part with its `overlay` attribute, using the **slug only** (no theme prefix): `{"overlayMenu":"mobile","overlay":"mobile-menu"}`.
4. Optional: register patterns with `Block Types: core/template-part/navigation-overlay` to offer alternative overlay designs in the editor.

See `references/architecture.md → Custom Navigation Overlays` for the full implementation reference.

---

## 3. Creating a Standalone Block Pattern (Section / Component)

Patterns are reusable sections inserted via the Block Inserter or called from templates.

### Pattern PHP header (required)

```php
<?php
/**
 * Title: Hero Section
 * Slug: {{THEME_SLUG}}/hero-section
 * Categories: {{THEME_SLUG}}
 * Keywords: hero, banner, header
 * Block Types: core/group
 */
?>
```

WordPress requires `Title` and `Slug`; the other fields are recommended. `Block Types` is optional but improves discoverability.

### Required attributes on the outermost block

Every standalone/sub-pattern's outermost block MUST have all three:

1. `"className": "is-style-{pattern-slug}"` — registers the variation class that scopes its CSS.
2. `"lock": {"move": true, "remove": true}` — prevents accidental deletion.
3. Its slug listed in the pattern-asset registry (`inc/pattern-assets.php`) — never `wp_enqueue_style()` directly for pattern-level CSS.

### Image paths in patterns

Always use PHP to output image URLs — never hardcode paths — and keep the image inside a block:

```php
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/hero.png' ) ); ?>" alt="<?php esc_attr_e( 'Hero', '{{TEXT_DOMAIN}}' ); ?>" /></figure>
<!-- /wp:image -->
```

---

## 4. theme.json Design System

Read `references/theme-json.md` for a full reference on color palettes, typography, spacing,
and block-specific targeting. Key rules:

- **`"version": 3`** is current (introduced in 6.6, still current in 7.1). Always pin `"$schema"` to a release — `https://schemas.wp.org/wp/7.0/theme.json`, or `wp/7.1` for a theme that requires 7.1. Never `trunk`.
- **CSS variable naming**: `var(--wp--preset--color--{slug})`, `var(--wp--preset--spacing--{slug})`.
- **Inject raw CSS**: Use `styles.css` property in `theme.json` for global rules, or
  `styles.blocks["core/group"].css` for block-scoped rules.
- **Child theme inheritance**: The child's `theme.json` merges with the parent's at the object/key level — it does not replace the whole file. Only override what you need. **However, array values (e.g. `palette`, `fontSizes`, `spacingSizes`) overwrite the parent array entirely rather than merging.** Copy the parent entries you still need and append your additions. A parent token the child omits stops generating its CSS variable.
- **Text indent (WP 7.0)**: two keys. `settings.typography.textIndent` chooses *which* paragraphs are indented — `"subsequent"` (only paragraphs following another paragraph) or `"all"`. `styles.blocks.core/paragraph.typography.textIndent` sets the indent *amount* (a length such as `"2em"`). See `references/theme-json.md → Text Indent`.
- **Dimension presets (WP 7.0)**: `settings.dimensions.dimensionSizes` defines width/height presets (`--wp--preset--dimension--{slug}`). See `references/theme-json.md → Preset Dimension Values`.
- **Responsive block styles (7.1+)**: `"@mobile"` / `"@tablet"` keys under a block's styles, and `settings.viewport` for breakpoints. See `references/core-7.1-apis.md`.

---

## 5. Debugging Common Issues

| Symptom | Cause | Fix |
|---|---|---|
| Template not in Page Attributes dropdown | Not registered in `theme.json` `customTemplates` | Add the entry; check the `name` matches the filename (without `.html`) |
| Raw HTML disappears in editor | Non-block HTML pasted directly into a template | Register a dynamic block (`register_block_type()` + `render_callback`) and emit the HTML from the callback |
| CSS not loading on frontend | Slug missing from the pattern-asset registry, or it does not match the folder / class | The registry key, the `patterns/{slug}/` folder and the `is-style-{slug}` class on the outermost block must be identical. Confirm `functions.php` requires `inc/pattern-assets.php`. |
| Editor doesn't match frontend | Stylesheet enqueued for the frontend only | Pattern CSS must go through the registry — the loader also enqueues it on `enqueue_block_assets`, which is what reaches the editor canvas. |
| theme.json style changes ignored | The user's Global Styles override the theme's | In the Site Editor's Styles panel use **Reset styles**. That discards the user's style customisations, so confirm first. |
| Old content persists after file change | A customised copy of the template or part is stored in the database | Use the template's **Reset** action in the Site Editor. Its revisions can restore earlier edits if the user wants them back. |
| Block never renders | `metadata.blockVisibility` is `false` | Remove the attribute to show the block. |
| Viewport hiding has no effect | `viewport` written as an array (`["desktop"]`) | Use the object form: `{"viewport": {"mobile": false, "tablet": false}}`. |
| Pattern images broken | Hardcoded path, or a parent-theme pattern using the child theme's directory | Use `get_theme_file_uri( 'assets/images/…' )` in the PHP pattern — it returns the child theme's copy when one exists and the parent's otherwise |
| Block validation error | Nesting issue or stray HTML | Validate markup; ensure every chunk of raw HTML lives inside a registered dynamic block, not loose in the template |
| **Template part renders as empty `<div class="wp-block-template-part">`** | `wp:template-part` placed inside a dynamic block's `render_callback` output | Move `wp:template-part` comments to the root of the `.html` template, outside any dynamic block |
| **Template part exists in DOM but content is empty** | Pattern is missing or typo in the slug | Check the `slug` inside the `wp:pattern` comment matches the PHP registration exactly |
| **Template part CSS not loading** | Outermost block missing style class, or slug not in the registry | The backing pattern's root block needs `is-style-{slug}` and `{slug}` must be in the pattern-asset registry. |
| **PHP functions not running in template part** | Trying to put PHP directly in `.html` file | `.html` files cannot run PHP — put PHP in a `.php` pattern and call it via `<!-- wp:pattern {"slug":"..."} /-->` |
| **`blockVisibility` PHP type error** | Treating `metadata.blockVisibility` as always an array — it can also be the boolean `false` | Check `false === $visibility` first, then `is_array( $visibility )`. The block **support** key is `visibility`; the **metadata attribute** is `blockVisibility`. |
| **Post editor canvas is not iframed (7.0)** | A block with `apiVersion` below 3 is in the post | 7.0 drops the iframe when such a block is present. Upgrade the block to API version 3. On 7.1+ the editor is always iframed. |
| **Pattern script never loads** | `'script' => true` / `'module' => true` missing from the registry entry, or the file is not `patterns/{slug}/index.js` / `view.js` | Fix the registry entry. `register_block_style()` cannot attach a script. |
| **White screen / parse error after deploying** | Code uses syntax newer than the server's PHP (e.g. property hooks on 8.3) | Establish the site's PHP version and rewrite with `references/php-compatibility.md`; declare `Requires PHP` in `style.css`. |
| **`core/icon` renders nothing** | Icon name is not registered | On 7.0 only built-in `core/{name}` icons exist. Custom icons need 7.1+ (`references/core-7.1-apis.md`). |

For DOM-level diagnostics, see the **Diagnostic Console Snippet** in `references/architecture.md`.

---

## 6. Modular Sub-Pattern Architecture

When a template is complex (e.g. a full landing page), decompose it into sub-patterns. WordPress requires the `.php` file for each pattern to exist in the `patterns/` directory to automatically register them:

```
patterns/
  my-landing-page.php        ← master pattern (assembles sub-patterns)
  hero-section.php           ← Hero PHP pattern (name MUST match its directory)
  faq-section.php            ← FAQ PHP pattern
  cta-section.php            ← CTA PHP pattern
  faq-section/
    style.css                ← Scoped CSS for FAQ only
    index.js                 ← FAQ JS (non-reactive, e.g. scroll reveal)
  cta-section/
    style.css                ← Scoped CSS for CTA only
  hero-section/
    style.css                ← Scoped CSS for Hero only
```

Because the pattern-asset loader enqueues by variation class, a page that uses only the
`faq-section` pattern loads only the FAQ CSS/JS, not the entire landing page bundle.

The master pattern calls each sub-pattern:

```php
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/hero-section"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/faq-section"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/cta-section"} /-->
```

This keeps individual files manageable and makes each section independently editable in the
Site Editor.

---

## 7. Plugin & AI Workflows (WP 7.0+)

WordPress 7.0 ships a plugin-side API surface alongside the theme features above. This section is the entry point for plugin work — each subsection is a thin pointer to a dedicated reference file. Load the matching reference **before** writing code.

### 7.1 Registering an Ability (server + client)

Abilities are the standard way to expose a capability — to AI agents, to the REST API, and to plugin JS — through one registry. Everything lives in `references/abilities-api.md`: PHP registration, REST exposure and method mapping, and the JavaScript client.

Three rules cause most failures:
- The **category slug has no slash** (`my-plugin-editorial`); the ability name has exactly one (`my-plugin/tone-shift`).
- Categories register on `wp_abilities_api_categories_init`, abilities on `wp_abilities_api_init`.
- An ability reaches REST and JavaScript only with `'meta' => array( 'show_in_rest' => true )`.

Always annotate `meta.annotations` honestly: clients and AI agents read those flags to decide when to ask for confirmation.

### 7.2 Calling the WP AI Client

Entry point is `wp_ai_client_prompt( $prompt = null )` → `WP_AI_Client_Prompt_Builder`. WordPress core bundles **no AI provider** — generation works only after a provider plugin is installed and given a key at Settings → Connectors. So **feature-detect on the builder instance before offering an AI feature** (showing its UI, enabling its control); the check makes no API call. Every generating call can still return `WP_Error`, and every call site handles it. For structured data use `->as_json_response( $schema )` and `json_decode()` the returned string. For metadata (token usage, provider, model) use the `*_result()` variant, which returns a `GenerativeAiResult`.

Common shape inside an ability's `execute_callback`:

```php
$builder = wp_ai_client_prompt()
	->using_system_instruction( '…' )
	->using_temperature( 0.4 )
	->with_text( $input['prompt'] )
	->as_json_response(
		array(
			'type'       => 'object',
			'properties' => array(
				'summary' => array( 'type' => 'string' ),
			),
		)
	);

if ( ! $builder->is_supported_for_text_generation() ) {
	return new \WP_Error( 'no_text_provider', __( 'No AI provider is available.', '{{TEXT_DOMAIN}}' ) );
}

$result = $builder->generate_text();
if ( is_wp_error( $result ) ) {
	return $result;
}
```

**Never** call `WP_AI_Client_Prompt_Builder::is_supported_for_text_generation()` statically — it is a fatal error.

Read `references/ai-client.md` when:
- The user wants to call an LLM from PHP (summarise, translate, classify, generate text/image/audio/video).
- The user mentions OpenAI / Anthropic / Gemini, API keys, or `Settings → Connectors`.
- The user wants a model to call their own functions (`->using_abilities()`).
- The user asks where to put API keys (env var vs PHP constant vs database), or how to cap AI cost.

### 7.3 Building a DataViews-powered admin screen

DataViews drives modern admin listings; DataForm is the matching form layer. It is an npm package to bundle (`@wordpress/dataviews`), not a core script handle. Key 7.0 facts: `groupBy` is an object (`{ field, direction, showLabel }`, replacing `groupByField`), `onReset` has three meaningful values (`undefined` / `false` / `function`), field validation lives in the field's **`isValid`** object (`required`, `pattern`, `minLength`/`maxLength`, `min`/`max`, `custom`), and `getValueFormatted( { item, field } )` separates the stored value from the displayed value.

Read `references/dataviews.md` when:
- The user is building or modifying a DataViews admin screen.
- The user mentions `groupBy`, `onReset`, field validation, or formatting bytes / dates / role labels.
- You see `groupByField` in existing code — it must be migrated.

### 7.4 Core Environment & Utilities

- **Core Environment Changes:** WordPress 7.0 and 7.1 run on PHP 7.4 through 8.5 and ship PHPMailer 7.0.2. *(This skill targets PHP 8.4 by default and supports 8.2 and 8.3 — see the PHP gate.)*
- **Breadcrumb Block Filters:** `block_core_breadcrumbs_items` receives **one argument** (the items array). If an item's `allow_html` flag is true, its label is sanitized via `wp_kses_post()`; otherwise it is escaped via `esc_html()`. `block_core_breadcrumbs_post_type_settings` chooses the taxonomy and term used in the trail.
- **User Registration Roles:** Administrator and Editor are removed from the New User Default Role selector (Settings → General). Change the excluded list with the `default_role_dropdown_excluded_roles` filter.
- **AI governance:** `wp_supports_ai()` reports whether AI has been switched off (constant `WP_AI_SUPPORT` or the `wp_supports_ai` filter); `wp_ai_client_prevent_prompt` blocks individual prompts (budget caps); `wp_ai_client_default_request_timeout` tunes timeouts. See `references/ai-client.md` §5.
- **Real-time collaboration is not in core.** It was removed before 7.0 shipped and is not enabled in 7.1. Never emit `wp_is_collaboration_enabled()`, `wp_is_collaboration_allowed()` or `WP_ALLOW_COLLABORATION`. To avoid overwriting an editor's work, check the post lock — `references/core-7.0-apis.md` §1.
- **Admin UX, security, media, speculative loading:** For admin View Transitions, the Command Palette, password-hashing filters (Argon2), image LCP / `fetchpriority`, image alt-text import, and prefetch/prerender configuration, load `references/core-7.0-apis.md`.

---

## 8. WooCommerce Block Theme Development

If the user's task involves WooCommerce — overriding shop/cart/checkout/single-product templates, composing `woocommerce/*` blocks, styling Cart/Checkout/Mini-Cart, registering JS filters via `registerCheckoutFilters()`, using conditional tags (`is_shop()`, `is_product()`, `is_cart()`), calling `wc_get_logger()`, or anything touching the `Automattic\WooCommerce` namespace — **load `references/woocommerce.md` before writing code**.

Five additional pre-write gates apply on top of the five in "How to approach a request":

- **Block name gate** — every `woocommerce/*` block name used in template markup must appear in `references/woocommerce.md` §3 or be confirmed in the plugin's own block metadata (§3 is a subset). Do not invent block names (common AI failure: `woocommerce/cart-button`, `woocommerce/checkout-form` — these are composed of smaller blocks, not single units).
- **Ancestor gate** — product element blocks (price, image, button, rating, sale-badge, sku, stock, summary, etc.) need a product context. Their `ancestor` list (`woocommerce/single-product`, `woocommerce/product-template`, `core/post-template`, etc.) restricts where the editor allows them, and on the front end they render nothing when no product can be resolved. Place them inside a declared ancestor; the Single Product template is the documented exception — see `references/woocommerce.md` §2.
- **Internal privacy gate** — the HTML structure inside a WooCommerce block is private and changes between releases. Prefer global styles (`theme.json`) and block style variations; where CSS is unavoidable, target only the documented class prefixes in `references/woocommerce.md` §5.1.
- **Conditional tag timing gate** — `is_shop()` / `is_product()` / `is_cart()` are only valid after the `posts_selection` action. Safe earliest hook is `wp`. Never call them at `functions.php` global scope. Guard calls to WooCommerce functions from theme code with `function_exists()`: the theme stays active when WooCommerce is off.
- **Placement gate** — code that changes what the store *does* (a checkout field, validation, anything written to an order) goes in a plugin by default — not the theme or a child theme — so it survives a theme switch; cosmetic changes (labels, price formats, notices, styling) may live in the theme. Only when the user names the theme as the place, write it there and say that it stops when the theme changes. Details: `references/woocommerce.md` §2.

Overriding a WooCommerce template (`single-product.html`, `archive-product.html`, `page-cart.html`, etc.) uses the standard template-override mechanism — drop a same-named file in `templates/` and it wins over the WooCommerce default. The master-pattern / sub-pattern architecture from Sections 0 and 6 applies unchanged; the new file just happens to override a WooCommerce default. WooCommerce's own Single Product template places blocks such as `woocommerce/product-price` directly, without a `woocommerce/single-product` wrapper.

`references/woocommerce.md` covers the **rendering surface** (blocks, templates, CSS, conditional tags, logging). For deeper WooCommerce work, load the focused companion file instead:

- **`references/woocommerce-checkout-fields.md`** — adding and validating **additional** checkout fields via `woocommerce_register_additional_checkout_field` (locations, persistence, sanitize/validate, JSON-Schema conditional logic, meta-key prefixes, stale-data trap, legacy compat). That function cannot change a built-in field; the file covers the separate route for those.
- **`references/woocommerce-checkout-lifecycle.md`** — the client-side checkout JS model: data stores (`wc/store/checkout`), status machine, observers (`onCheckoutValidation` / `onPaymentSetup` / `onCheckoutSuccess`), SlotFill components, and native DOM events.
- **`references/woocommerce-performance.md`** — store caching/cookie exclusions, DB hygiene, GZIP, asset optimization, Core Web Vitals (store/infra config, not template authoring).
- **`references/woocommerce-standards.md`** — safe customization (child themes, hooks, snippets), PHP/JS naming, isolation mandates, Webpack dependency extraction, `is_ssl()` behind load balancers, and the QA hierarchy.

---

## Reference Files

| File | Load when… |
|---|---|
| `references/php-compatibility.md` | The site's PHP version is below 8.4 or unknown, or the user asks which PHP features may be used. Covers how to establish and record the version, the 8.4 / 8.3 / 8.2 fallbacks, and deprecated constructs to avoid. |
| `references/php-coding-standards.md` | Before writing any PHP — `functions.php`, `inc/**/*.php`, `render_callback` closures, or any PHP pattern file that contains function/class definitions. |
| `references/coding-standards.md` | Before writing any CSS or real HTML markup (PHP patterns, render callbacks, `<img>` etc.). |
| `references/js-coding-standards.md` | Before writing any JavaScript — `view.js` Interactivity stores, `index.js` non-reactive scripts, WooCommerce checkout JS, or abilities client-side code. |
| `references/html-conversion.md` | The user provides raw HTML/CSS/JS to convert. |
| `references/architecture.md` | Before writing ANY theme code (mandatory pre-write read). Holds the pattern-asset loader. |
| `references/theme-json.md` | Modifying `theme.json` beyond a `customTemplates` entry. |
| `references/api-allowlist.md` | Before every WordPress function, hook, block name or `theme.json` key you are not certain exists. |
| `references/scaffold-tree.md` | Creating a new theme directory, a companion plugin or a build step, or adding new top-level folders. |
| `references/ai-client.md` | The user wants to call an LLM from PHP, let a model call abilities, work with Connectors, or manage AI API keys and cost. |
| `references/abilities-api.md` | The user is registering an ability (PHP or JavaScript), exposing one over REST, or needs the REST method-mapping rules for `readonly` / `destructive` / `idempotent` annotations. |
| `references/dataviews.md` | The user is building a DataViews admin screen, configuring `groupBy` / `onReset`, adding field validation, or formatting displayed values with `getValueFormatted`. |
| `references/core-7.0-apis.md` | The user asks about real-time collaboration, admin View Transitions, the Command Palette, password hashing / Argon2, image LCP / `fetchpriority` / alt text, or speculative loading. |
| `references/core-7.1-apis.md` | The site runs WordPress 7.1 or later and the task involves custom icons, `core/playlist` / `core/tabs`, responsive block styles, viewport breakpoints, or other 7.1-only `theme.json` keys. |
| `references/woocommerce.md` | The user is working with WooCommerce — overriding shop / cart / checkout / single-product / order-confirmation templates, composing `woocommerce/*` blocks, styling Cart/Checkout/Mini-Cart, registering Cart/Checkout filters, using conditional tags (`is_shop()` / `is_product()` / `is_cart()`), or calling `wc_get_logger()`. |
| `references/woocommerce-checkout-fields.md` | The user is adding, validating, or reading a custom Cart/Checkout field, or changing a built-in one — `woocommerce_register_additional_checkout_field`, sanitize/validate callbacks, JSON-Schema conditional fields, or checkout meta-key access. |
| `references/woocommerce-checkout-lifecycle.md` | The user is writing Cart/Checkout JS — checkout status/observers (`onCheckoutValidation` / `onPaymentSetup` / `onCheckoutSuccess`), `wc/store/checkout` data store, SlotFill (`ExperimentalOrderMeta`, …), or native cart DOM events. |
| `references/woocommerce-performance.md` | The user is configuring store caching/cookie exclusions, fixing a cache-induced cart/login bug, or optimizing a store's Core Web Vitals / assets / database. |
| `references/woocommerce-standards.md` | The user needs safe-customization guidance (child themes / hooks / snippets), WooCommerce PHP/JS naming, conflict-free isolation, the dependency-extraction build, `is_ssl()` behind a load balancer, or QA/testing setup. |
