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
---

# WordPress Block Theme Developer
*Standard: WordPress 7.0 Enterprise (May 2026)*

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
3. **Follow the architecture rules** in `references/architecture.md`. If the user is on **WordPress 6.9**, prioritize the fallback patterns in `references/compatibility-6.9.md`.
4. **Execute** using the implementation checklists below.
5. **Verify** — run a quick filesystem audit and remind the user of the Site Editor "Clear
   Customizations" trick if they report that file changes are not showing.

---

## Core Architectural Rules (memorise these)

- **Identify the Target Context:** All work must respect the **Parent/Child boundary**.
  - **Child Theme Context:** If `AI_CONTEXT-Child.md` exists and is the active focus, **never touch the parent theme directory**. All modifications go in the child theme.
  - **Parent Theme Context:** If you are explicitly developing the parent core (active folder is `egnitech-one` and task is foundational), you may modify parent files. Maintain a "Child-Theme-First" mindset: if a feature is project-specific, suggest moving it to a child theme instead.
- **Native-First Architecture (The 1% Rule):** Recreate designs using Core Blocks. In 2026, this means using **Native Grid Layouts** (`core/group` with grid type) and **Native Component Blocks** (e.g., `core/accordion`) instead of custom CSS/JS. Avoid `wp:html` entirely; use **Native PHP-only Blocks** (mapped via `block.json`) for complex layouts that cannot be represented by core blocks.
- **Every custom template must be registered** via the **Template Registration API (PHP)** to enable A/B testing segments and AI-driven layout variants.
- **Content-Only Locking (Phase 3 Standard):** Use `"templateLock": "contentOnly"` on structural patterns to allow multi-user collaboration without breaking layouts.
- **AI-Native Infrastructure (The Abilities API):** Register AI "intents" (Tone Shift, Summarize) for patterns. Ensure patterns include **Intent Metadata** to allow the WordPress AI Sidekick to suggest contextual shifts.
- **Block Locking (Client-Proofing):** All master patterns should include `"lock": {"move": true, "remove": true}` to prevent accidental deletion.
- **CSS scoping via Block Style Variations:** Scope CSS strictly to the block's variation class (e.g. `.is-style-hero-section`). This ensures component isolation and automatic editor injection.
- **Atomic Asset Loading**: For complex sections, use the "Pattern-First" asset model. For mandatory pattern logic, use **Block Hooks** to wrap patterns in logic-providing blocks automatically.
- **Modern Interactions (Interactivity API):** For frontend logic, use the **Interactivity API**. Elite 2026 usage relies on native **Script Modules** linked to block presence, replacing manual `render_block` hacks.
- **Synced Pattern Overrides:** For repeating sections (Hero, CTA), use **Synced Patterns with Overrides** (supporting **Nested Overrides** for blocks inside cards/grids). This allows "Locked Design / Flexible Content."
- **Dynamic Data (Block Bindings):** Use the **Block Bindings API** for Post Meta and Metadata. For Icons, use the native **core/icon block** and register your theme's library into the **WP_Icons_Registry** for a searchable native UI.
- **Top 1% Quality Architecture**: For global areas (Headers, Footers), always use **Template Parts (`/parts`)** registered in `theme.json`.
- **Template Part → Pattern rendering chain**: Use PHP patterns to power `.html` template parts when you need logic like `get_stylesheet_directory_uri()`.
- **System vs. Functional Patterns:** Use `Inserter: false` for "assembler" patterns (logic only). However, **Synced Patterns with Overrides** must have `Inserter: true` so clients can re-add them if accidentally deleted.
- **theme.json Token Inheritance (The 1% Rule):** In child themes, arrays like `palette` overwrite the parent. To preserve inheritance while adding colors, use the `defaultPalette: true` flag strategically or reference parent tokens using `--wp--preset--color--{slug}`.
- **Fluid Design Tokens**: Use Fluid Typography and Fluid Spacing in `theme.json` to ensure designs scale perfectly across screen sizes without manual media queries.
- **Slugs and Text Domains:** Always prefix pattern slugs and categories with the current theme's directory name (e.g., `egnitech-one/` for parent, `egnitech-one-child/` for child). Use the corresponding text domain for localization.
- **Zero-CSS Layouts (The 1% Rule):** If it can be done in `theme.json`, it must not be in `style.css`. Use **Section Styles** (Variations inside `theme.json`) to define padding, margins, and layout values.

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
- [ ] Design mapped to Core Blocks (Group, Columns, etc.); remaining logic wrapped in **Native PHP-only Blocks** (last resort)
- [ ] Layout CSS is strictly scoped to the block style variation class (e.g. `.is-style-hero-section`), avoiding a catch-all page wrapper to prevent style bleed into independent sub-patterns
- [ ] Modular patterns (CTA, Header, FAQ, etc.) MUST use their own separate CSS class (e.g. `.is-style-main-cta`) and must NOT be placed inside a page-specific wrapper that alters their layout
- [ ] Master patterns (assemblers) do NOT have layout CSS. Delegate all styles to sub-patterns.
- [ ] Sub-pattern CSS saved to their specific directories (e.g., `patterns/faq-section/style.css`)
- [ ] Every sub-pattern's outermost block has a unique block style class (e.g. `is-style-{pattern-slug}`)
- [ ] JS wrapped in `DOMContentLoaded`, saved to `patterns/{sub-pattern}/index.js`
- [ ] Image paths use `get_stylesheet_directory_uri()` — no hardcoded URLs
- [ ] `theme.json` has the `customTemplates` entry
- [ ] `theme.json` uses **Version 4** (Schema 7.0)
- [ ] Asset loading for JS handled by **Script Modules API** (registered in PHP, enqueued via block presence or `viewScriptModule`)
- [ ] Icons registered in **WP_Icons_Registry** for native `core/icon` usage
- [ ] Pattern styles registered in `functions.php` using `register_block_style()` (WordPress handles this natively for CSS)

### Common conversion pitfalls

| Problem | Symptom | Fix |
|---|---|---|
| "Black Box" Templates | Can't edit colors/text in UI | Rebuild `wp:html` sections using Core Blocks or **Native PHP-only Blocks** |
| Raw HTML stripped | Content disappears in editor | Wrap in **Native PHP-only Block** (avoid `wp:html`) |
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
  <!-- wp:pattern {"slug":"{theme-slug}/{slug}"} /-->
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
 * Slug: {theme-slug}/{slug}
 * Categories: {theme-slug}
 * Keywords: landing, page, {keywords}
 * Inserter: false
 */
?>
<!-- No global wrapper. Master patterns only assemble sub-patterns to prevent CSS bleed. -->
<!-- wp:pattern {"slug":"{theme-slug}/hero-section"} /-->
<!-- wp:pattern {"slug":"{theme-slug}/another-section"} /-->
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
<!-- wp:pattern {"slug":"{theme-slug}/header-top-bar-variant-a"} /-->
```

**`patterns/header-top-bar-variant-a.php`** — the real content with PHP:
```php
<?php
/**
 * Title: Header Top Bar Variant A
 * Slug: {theme-slug}/header-top-bar-variant-a
 * Categories: {theme-slug}
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
<!-- wp:pattern {"slug":"egnitech-one-child/{slug}"} /-->
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
 * Slug: {theme-slug}/hero-section
 * Categories: {theme-slug}
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

- **Version 4** is current. Always use `"$schema": "https://schemas.wp.org/wp/7.0/theme.json"`.
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
| Raw HTML disappears in editor | Missing **Native PHP-only Block** wrapper | Wrap non-block HTML in a registered PHP-only block |
| CSS not loading on frontend | Block Style not registered | Check `functions.php` to ensure `register_block_style()` is hooked correctly and the variation class (e.g. `is-style-my-pattern`) is applied to the pattern's outermost block. |
| Editor doesn't match frontend | Styles not enqueued via block styles | Ensure CSS is loaded via `register_block_style()`; do not rely on global `add_editor_style()` for pattern-level CSS. |
| theme.json changes ignored | Database override active | Go to Site Editor → Styles → ⋮ menu → "Reset to defaults" / "Clear Customizations" |
| Pattern images broken | Hardcoded path | Use `get_stylesheet_directory_uri()` in PHP pattern |
| Block validation error | Nesting issue or stray HTML | Validate markup; ensure every raw HTML is inside a **PHP-only Block** |
| **Template part renders as empty `<div class="wp-block-template-part">`** | `wp:template-part` placed inside a **PHP-only Block** | Move `wp:template-part` comments to root block scope |
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
<!-- wp:pattern {"slug":"egnitech-one-child/hero-section"} /-->
<!-- wp:pattern {"slug":"egnitech-one-child/features-section"} /-->
<!-- wp:pattern {"slug":"egnitech-one-child/cta-section"} /-->
```

This keeps individual files manageable and makes each section independently editable in the
Site Editor.

---

## Reference Files

- `references/html-conversion.md` — Full 10-step HTML→block template conversion with worked example
- `references/architecture.md` — FSE architecture rules, asset pipeline template, diagnostic snippet
- `references/theme-json.md` — Full theme.json schema reference (colors, typography, spacing, layout)
- `references/compatibility-6.9.md` — Fallback patterns for WordPress 6.9 sites (no Icons Registry, no PHP Blocks)
