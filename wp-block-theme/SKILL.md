---
name: wp-block-theme
description: >
  Expert WordPress Full Site Editing (FSE) block theme and template developer. Use this skill
  whenever the user wants to create, modify, or scaffold any part of a WordPress block theme:
  new templates (custom page templates, archive views, 404 pages), template parts (header,
  footer), block patterns (PHP-based reusable sections), or child theme extensions. Also triggers
  when the user wants to configure theme.json (colors, typography, spacing, layout), integrate
  custom CSS/JS assets scoped to a template, set up modular asset enqueuing in functions.php,
  or debug Site Editor sync issues. CRITICALLY: use this skill whenever the user provides raw
  HTML, CSS, or JS and asks to "convert it to a block theme template", "turn this into a
  WordPress template", "make this work in the Site Editor", or "add this design as a new page".
  If the user pastes HTML markup, a Figma export, or a static design file and wants it inside
  WordPress FSE — this is the skill to use.

## Required Context (Dynamic Variables)

Before executing this skill, you MUST read the `AI_CONTEXT.md` (or `AI_CONTEXT-Child.md`) to resolve the following variables:

- `{{THEME_SLUG}}`: The kebab-case slug of the active theme (e.g., `my-theme`).
- `{{THEME_NAME}}`: The human-readable name of the theme.
- `{{TEXT_DOMAIN}}`: The text domain used for localization.
- `{{PARENT_THEME_SLUG}}`: (Optional) The slug of the parent theme if working in a child theme.

All examples below use these placeholders. Replace them with actual values from the project context.
---

# WordPress Block Theme Developer
*Default target: WordPress 7.0 (released 2026-05-20). If the user explicitly states they are on WordPress 6.9 or earlier, read `references/compatibility-6.9.md` and substitute the documented fallbacks.*

A skill for creating and extending WordPress Full Site Editing (FSE) block themes — templates,
template parts, block patterns, theme.json design systems, and modular asset pipelines.

The most common entry point is **converting an existing HTML/CSS/JS design into a block theme
template**. This is the primary workflow covered in Section 0. All other sections cover the
underlying building blocks used during that process.

## How to approach a request

1. **Identify the deliverable** — Is this an HTML→template conversion, a new template from
   scratch, a pattern, a template part, a theme.json change, or an asset pipeline fix?
2. **Determine Target Theme & Read Context** — Identify if you are developing the **Core (Parent Theme)** or an **Extension (Child Theme)**. 
   - Read `AI_CONTEXT-Child.md` if working on a child theme.
   - Read `AI_CONTEXT.md` if working on the parent theme core.
   - Always prioritize child themes for project-specific features unless you are building foundational capabilities into the parent.
3. **Follow the architecture rules** in `references/architecture.md`. Only load `references/compatibility-6.9.md` if the user has explicitly stated they are on WordPress 6.9 or earlier.
4. **Pre-write gates.** Before writing any code, all four gates must pass. If any gate fails, fix it before proceeding:
   - **API gate:** Every WordPress function or class name must appear in `references/api-allowlist.md`. The "Names that look real but are not" section lists fabricated APIs to never use (`register_block_ability`, `WP_Icons_Registry::register`, `is_ai_ready`, `__experimentalRole`, `"version": 4`).
   - **PHP 8.3 gate:** Every PHP file must start with `declare(strict_types=1);`. Every named function and anonymous callback must have typed parameters and a return type. Use `??`, `str_contains()`, `match`, and `?->` where appropriate. Full checklist: `references/architecture.md → PHP 8.3 Requirements`.
   - **Version gate:** `"version": 3` in all `theme.json` files. Never write `"version": 4`.
   - **WP 7.0 gate:** Confirm each feature exists in WP 7.0. If the user is on 6.9 or earlier, load `references/compatibility-6.9.md` and substitute the documented fallbacks.
5. **Execute** using the implementation checklists below. Use `references/scaffold-tree.md` for the directory layout.
6. **Verify** — run a quick filesystem audit and remind the user to check the Site Editor Revisions panel (⋮ → "Revisions") before using "Clear Customizations" if they report that file changes are not showing.

---

## Core Architectural Rules

- **Respect the Parent/Child boundary.**
  - Child Theme Context: if `AI_CONTEXT-Child.md` exists and is the active focus, never touch the parent theme directory. All modifications go in the child theme.
  - Parent Theme Context: if you are explicitly developing the parent core (active folder matches the theme folder and task is foundational), you may modify parent files. If a feature is project-specific, suggest moving it to a child theme instead.
- **Native-first conversion.** Recreate designs using Core Blocks (`core/group`, `core/columns`, `core/heading`, `core/headings` (for togglable heading levels), `core/image`, `core/icon`, etc.). Use the Grid layout (`{"layout": {"type": "grid"}}` on `core/group`) for grid layouts. Avoid `wp:html`; for complex sections that cannot be represented by core blocks, register a dynamic block server-side via `register_block_type()` with a `render_callback`. This keeps colors, typography, and spacing under Site Editor control. See `references/architecture.md → Native-First Decision Algorithm` for the full decision tree.
- **Register custom templates via PHP.** Use `register_block_template()` (introduced 6.7) for any template that needs to be available across multiple themes or environments. See `references/architecture.md` for the full signature.
- **`contentOnly` is now the default for unsynced patterns (WP 7.0).** Do NOT add `"templateLock": "contentOnly"` to unsynced patterns — it is already active. Apply it explicitly only to synced patterns and template parts where structure must be locked. To opt out: use `"__experimentalSettings": {"disableContentOnlyForUnsyncedPatterns": true}` on the outermost block (per-pattern, experimental), or the stable `disableContentOnlyForUnsyncedPatterns` PHP/JS filter (site-wide). **Custom blocks nested inside contentOnly patterns MUST declare `"role": "content"` on every editable attribute in `block.json`** — without this the block is hidden from List View and non-selectable, with no error. See `references/architecture.md → Content-Only Locking` for the full decision tree and block author requirements.
- **Block locking.** Apply `"lock": {"move": true, "remove": true}` to the outermost wrapper of master patterns and template parts so accidental deletion cannot break the layout.
- **CSS scoping via Block Style Variations.** Scope CSS strictly to the block's variation class (e.g. `.is-style-hero-section`) and register that style with `register_block_style()`. WordPress then injects the CSS automatically into both the frontend and the editor iframe.
- **Modern interactivity.** Use the Interactivity API for any frontend JavaScript. Register scripts as **Script Modules** with `wp_register_script_module()` and bind them via the `script_module_handle` parameter on `register_block_style()` so they load only when the block is on the page.
- **Block Hooks** can automatically attach a logic-providing block before/after a target block — useful for mandatory wiring that must not be missed by editors. In WP 7.0, hooks fire for all CPTs registered with `'show_in_rest' => true` and `'supports' => ['editor']` — not just posts and pages.
- **Viewport block visibility.** Use `metadata.blockVisibility.viewport` to show/hide blocks by device type (`"mobile"`, `"tablet"`, `"desktop"`). Hiding is **CSS-based** — blocks remain in the DOM on all devices and are visually suppressed via an injected CSS class. Do not use CSS `display: none` on breakpoints for this purpose, and do not use this feature for access control. Enable with `settings.blockVisibility.viewport: true` in `theme.json`.
- **Font Library.** The Font Library is enabled for all theme types in WP 7.0 (block, hybrid, and classic). Use `theme.json fontFamilies` for version-controlled font registration. When a user says "install a font", direct them to Appearance → Editor → Styles → Typography → Manage Fonts. Do not enqueue fonts via `wp_enqueue_style` that already exist in the Font Library.
- **Synced Pattern Overrides.** For repeating sections (Hero, CTA), use synced patterns and mark overridable blocks with `metadata.bindings` + `metadata.name`. Set `Inserter: true` on these patterns so editors can re-insert them if deleted.
- **Block Bindings API** wires Post Meta and other dynamic sources directly into Core Blocks — no custom PHP patterns required.
- **Template Parts for global areas** (Headers, Footers). Register them in `theme.json` under `templateParts`. If a part needs PHP (image URLs, dynamic content), make the `.html` file a thin pointer that calls a PHP pattern.
- **System vs. functional patterns.** Use `Inserter: false` for assembler patterns that exist only to power templates. Synced patterns must keep `Inserter: true`.
- **theme.json token inheritance in child themes.** Arrays like `palette` overwrite the parent rather than merge. Either copy the parent palette and append, or set `"defaultPalette": true` and reference parent tokens via `var(--wp--preset--color--{slug})`.
- **Fluid Typography and Spacing.** Enable `typography.fluid: true` and define spacing tokens with `clamp()` so designs scale without manual media queries.
- **Slugs and text domains.** Prefix pattern slugs and categories with the current theme's directory name (e.g., `my-theme/` for parent, `my-theme-child/` for child). Use the corresponding text domain for localization.
- **Push layout to theme.json.** Padding, margin, and gap belong in `theme.json` Section Styles (variations under `styles.blocks.{block}.variations.{name}`). Reserve `style.css` for things JSON cannot express (gradients, complex transforms, scoped overrides).

---

## 0. Converting HTML/CSS/JS into a Block Theme Template

This is the primary workflow when the user provides a finished design (HTML export, static page,
Figma handoff, etc.) and wants it running inside WordPress FSE.

WordPress's block editor strips any raw HTML it doesn't recognise as a registered block. A
static HTML file dropped straight into a template file will be silently gutted. The conversion
process wraps the HTML correctly, scopes the CSS so it doesn't pollute the Site Editor, and
wires up asset loading so everything works on the frontend AND inside the editor iframe.

**Read `references/html-conversion.md` now** — it contains the full 10-step process with a
complete worked example (input HTML → all output files). Use it as your primary guide.

### Quick-reference checklist

Before handing off, verify every item:

- [ ] Agreed on a kebab-case **slug** with the user
- [ ] `templates/{slug}.html` exists — calls master pattern + `wp:post-content`
- [ ] `patterns/{slug}.php` (master) exists with `Inserter: false`
- [ ] All logical sections have sub-pattern files in `patterns/`
- [ ] Design mapped to Core Blocks (Group, Columns, etc.); any remaining custom HTML wrapped in a dynamic block registered via `register_block_type()` (last resort)
- [ ] Layout CSS is strictly scoped to the block style variation class (e.g. `.is-style-hero-section`), avoiding a catch-all page wrapper to prevent style bleed into independent sub-patterns
- [ ] Modular patterns (CTA, Header, FAQ, etc.) use their own separate CSS class (e.g. `.is-style-main-cta`) and are not placed inside a page-specific wrapper that alters their layout
- [ ] Master patterns (assemblers) have no layout CSS — all styles live in sub-patterns
- [ ] Sub-pattern CSS saved to its own directory (e.g., `patterns/faq-section/style.css`)
- [ ] Every sub-pattern's outermost block has a unique block style class (e.g. `is-style-{pattern-slug}`)
- [ ] JS wrapped in `DOMContentLoaded` (or registered as a Script Module), saved to `patterns/{sub-pattern}/index.js`
- [ ] Image paths use `get_stylesheet_directory_uri()` — no hardcoded URLs
- [ ] `theme.json` has the `customTemplates` entry
- [ ] `theme.json` uses `"version": 3` and `"$schema": "https://schemas.wp.org/trunk/theme.json"`
- [ ] JS handled by **Script Modules API** (`wp_register_script_module()`, enqueued via block presence or `viewScriptModule`)
- [ ] Pattern styles registered in `functions.php` using `register_block_style()` (WordPress injects the CSS into the editor iframe automatically)

### Common conversion pitfalls

| Problem | Symptom | Fix |
|---|---|---|
| "Black box" templates | Can't edit colors/text in the Site Editor | Rebuild custom sections using Core Blocks; only fall back to a dynamic block (`register_block_type()` + `render_callback`) when the design truly cannot map to core. |
| Raw HTML stripped | Content disappears in editor | Wrap in a dynamic block registered via `register_block_type()`; do not paste raw HTML into a template. |
| CSS bleeds into editor UI | Styles affect menus/toolbars | Scope rules strictly to `.is-style-{slug}` variation class |
| Template not accessible | A11y warnings/fail | Use semantic tags (nav, main) and ARIA labels |
| Client breaks layout | Sections deleted accidentally | Apply `"lock": {"move": true, "remove": true}` to structural blocks |
| Editor doesn't match frontend | WYSIWYG mismatch | Ensure CSS is loaded via `register_block_style()` |
| Animations fire in editor | Jarring editor experience | Gate logic on `window.frameElement` or use Interactivity API state |
| theme.json colors missing | Parent palette deleted | Child `theme.json` must include parent palette entries before appending |

---

## 1. Creating a New Custom Page Template (from scratch)

### Files to create / modify

| File | Action |
|---|---|
| `templates/{slug}.html` | New — the template entry point |
| `patterns/{slug}.php` | New — the main pattern (assembler) |
| `theme.json` | Modify — add entry under `customTemplates` |
| `functions.php` | Modify — register block styles for pattern assets |

### Step-by-step checklist

- [ ] **Determine the slug** — kebab-case, e.g. `my-landing-page`.
- [ ] **Create `templates/{slug}.html`**: Note: The developer must explicitly decide which header/footer to include here.
  > **UX Warning:** Hardcoding layout patterns (like a Hero) inside this `.html` template locks them to the Site Editor. Content creators using the normal Page Editor will only see a blank box for `wp:post-content`. If editors need to easily modify the Hero text natively in the Page Editor, do not include the layout pattern here—instruct them to insert the pattern directly into the page content itself instead.
  ```html
  <!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
  <!-- wp:pattern {"slug":"{{THEME_SLUG}}/{slug}"} /-->
  <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
  <!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
  ```
- [ ] **Create `patterns/{slug}.php`** — see pattern header format below.
- [ ] **Create sub-pattern CSS/JS** as needed in `patterns/{sub-pattern}/`
- [ ] **Register in `theme.json`** under `customTemplates`:
  ```json
  { "name": "{slug}", "title": "Human-Readable Title", "postTypes": ["page"] }
  ```
- [ ] **Enqueue assets in `functions.php`** — Use `register_block_style()` for CSS. For JS, use `wp_register_script_module()` and link the module to your block variation. For mandatory logic, use **Block Hooks** to automate logic injection regardless of the selected variation.

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

See `references/architecture.md` (Asset Pipeline section) for the full `functions.php` template.

---

## 2. Creating a Template Part (Header / Footer / etc.)

Template parts live in `parts/` and must be registered in `theme.json` under `templateParts`.

### Files

| File | Action |
|---|---|
| `parts/{name}.html` | New — the part markup |
| `patterns/{name}.php` | New — PHP pattern with the actual HTML/SVGs (called by the part) |
| `patterns/{name}/style.css` | New — scoped CSS for this component |
| `patterns/{name}/index.js` | New (if JS needed) |
| `theme.json` | Modify — add to `templateParts` |
| `functions.php` | Modify — register block styles for template part backing patterns |

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
<!-- wp:group {"className":"is-style-header-top-bar-variant-a"} -->
<div class="wp-block-group is-style-header-top-bar-variant-a">
    <!-- wp:icon {"icon":"my-theme/warning"} /-->
    <p>FOR LABORATORY RESEARCH USE ONLY.</p>
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

Never wrap global template parts (like Headers or Footers) inside a master pattern's `<div class="{slug}-wrapper">`. If you do, the wrapper's scoped CSS will bleed into the global parts and break them, making them dependent on that specific template wrapper.

Instead, always call template parts directly in the root `templates/{slug}.html` file, keeping them completely outside the master pattern's wrapper:

```html
<!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/{slug}"} /-->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
<!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
```

This ensures true modularity, preventing CSS scoping conflicts while letting the developer decide which header and footer to use on a per-template basis.

### Asset Enqueuing for Template Parts

Because the template part relies on a backing PHP pattern, you register its CSS exactly the same way as any standard pattern: by registering a block style variation for the outermost block of the PHP pattern. This eliminates the need for custom asset loaders or bare-slug normalisation arrays.

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

Valid `area` values: `header`, `footer`, `uncategorized`.

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

All five header fields are recommended. `Block Types` is optional but improves discoverability.

### Image paths in patterns

Always use PHP to output image URLs — never hardcode paths:

```php
<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/assets/images/hero.png" alt="Hero">
```

---

## 4. theme.json Design System

Read `references/theme-json.md` for a full reference on color palettes, typography, spacing,
and block-specific targeting. Key rules:

- **`"version": 3`** is current (introduced in 6.6, still current in 7.0). Always use `"$schema": "https://schemas.wp.org/trunk/theme.json"`.
- **CSS variable naming**: `var(--wp--preset--color--{slug})`, `var(--wp--preset--spacing--{slug})`.
- **Inject raw CSS**: Use `styles.css` property in `theme.json` for global rules, or
  `styles.blocks["core/group"].css` for block-scoped rules.
- **Child theme inheritance**: The child's `theme.json` merges with the parent's — it does not
  replace it. Only override what you need.

---

## 5. Debugging Common Issues

| Symptom | Cause | Fix |
|---|---|---|
| Template not in Page Attributes dropdown | Not registered in `theme.json` `customTemplates` | Add the entry; check the `name` matches the filename (without `.html`) |
| Raw HTML disappears in editor | Non-block HTML pasted directly into a template | Register a dynamic block (`register_block_type()` + `render_callback`) and emit the HTML from the callback |
| CSS not loading on frontend | Block Style not registered | Check `functions.php` to ensure `register_block_style()` is hooked correctly and the variation class (e.g. `is-style-my-pattern`) is applied to the pattern's outermost block. |
| Editor doesn't match frontend | Styles not enqueued via block styles | Ensure CSS is loaded via `register_block_style()`; do not rely on global `add_editor_style()` for pattern-level CSS. |
| theme.json changes ignored | Database override active | 1. Check Revisions panel first (Site Editor → ⋮ → "Revisions") — roll back to "Original" if a revision is overriding. 2. If no revision, go to Site Editor → Styles → ⋮ → "Reset to defaults" / "Clear Customizations". |
| Old content persists after file change | Database revision overriding file | Open Site Editor → select template/part/pattern → ⋮ → "Revisions" → roll back to "Original". Use "Clear Customizations" only if no usable revision exists — it is not reversible. |
| Block hidden on all devices unexpectedly | `blockVisibility.viewport` set to empty array | Check `metadata.blockVisibility.viewport` on the block — `[]` hides everywhere. Remove the key entirely to show on all devices. |
| Pattern images broken | Hardcoded path | Use `get_stylesheet_directory_uri()` in PHP pattern |
| Block validation error | Nesting issue or stray HTML | Validate markup; ensure every chunk of raw HTML lives inside a registered dynamic block, not loose in the template |
| **Template part renders as empty `<div class="wp-block-template-part">`** | `wp:template-part` placed inside a dynamic block's `render_callback` output | Move `wp:template-part` comments to the root of the `.html` template, outside any dynamic block |
| **Template part exists in DOM but content is empty** | Pattern is missing or typo in the slug | Check the `slug` inside the `wp:pattern` comment matches the PHP registration exactly |
| **Template part CSS not loading** | Outermost block missing style class | Ensure the PHP pattern powering the template part has a variation class like `is-style-header` on its root block. |
| **PHP functions not running in template part** | Trying to put PHP directly in `.html` file | `.html` files cannot run PHP — put PHP in a `.php` pattern and call it via `<!-- wp:pattern {"slug":"..."} /-->` |

For DOM-level diagnostics, see the **Diagnostic Console Snippet** in `references/architecture.md`.

---

## 6. Modular Sub-Pattern Architecture

When a template is complex (e.g. a full landing page), decompose it into sub-patterns. WordPress requires the `.php` file for each pattern to exist in the `patterns/` directory to automatically register them:

```
patterns/
  my-landing-page.php        ← master pattern (assembles sub-patterns)
  faq-section.php            ← FAQ PHP pattern (name MUST match directory)
  cta-section.php            ← CTA PHP pattern
  hero-section.php           ← Hero PHP pattern
  faq-section/
    style.css                ← Scoped CSS for FAQ only
    index.js                 ← FAQ JS (e.g. accordion)
  cta-section/
    style.css                ← Scoped CSS for CTA only
  hero-section/
    style.css                ← Scoped CSS for Hero only
```

This ensures that if you use the `faq-section` pattern on a different page, only the FAQ CSS/JS
loads, not the entire landing page bundle.

The master pattern calls each sub-pattern:

```php
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/hero-section"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/features-section"} /-->
<!-- wp:pattern {"slug":"{{THEME_SLUG}}/cta-section"} /-->
```

This keeps individual files manageable and makes each section independently editable in the
Site Editor.

---

## Reference Files

- `references/html-conversion.md` — 10-step HTML→block template conversion with a worked example
- `references/architecture.md` — FSE architecture rules: registration APIs, asset pipeline, Bindings, Pattern Overrides, Abilities API, Interactivity, Speculation Rules, diagnostic snippet
- `references/theme-json.md` — Full theme.json schema reference (colors, typography, spacing, layout, section variations)
- `references/api-allowlist.md` — Verified WordPress function/class names. **Consult before writing any WP API call.**
- `references/scaffold-tree.md` — Copy-pasteable directory layouts for parent/child themes and dynamic blocks
- `references/compatibility-6.9.md` — Fallback patterns for WordPress 6.9 sites. Load only when the user explicitly states they're on 6.9 or earlier.
