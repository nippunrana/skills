# WordPress Block Theme Developer Skill — AI Agent Feedback & Refactoring Plan

This document provides a highly technical, structured code review and refactoring plan for the `wp-block-theme` developer skill. It is designed to be consumed directly by the AI agent responsible for maintaining this skill, enabling it to clean up contradictions, transition 100% to **WordPress 7.0 native architecture**, and eliminate all legacy fallbacks.

---

## 1. Executive Summary

While the current skill framework is comprehensive, it contains several critical contradictions, legacy design patterns, and fallback systems that degrade the performance of AI agents. 

To achieve **"Top 1% developer status"** and ensure absolute reliability for AI execution on WordPress 7.0 (launching May 20, 2026), the skill must be refactored to:
1. **Eliminate All Fallbacks:** Fully purge the WordPress 6.9 compatibility layer and remove the fallback reference file.
2. **Resolve Structural Contradictions & Fabricated APIs:** Standardize namespaces, schemas, strict types, and JavaScript roles across all reference files, and remove the fabricated `wp_register_icons` filter.
3. **Enforce Single Source of Truth (SSOT):** Ensure that the scaffold tree, architecture guidelines, html conversion guides, and evaluation files do not contradict each other.

---

## 2. Conflicting Rules & Contradictions (The "Confusion Matrix")

The following table details the active conflicts between the core files, why they confuse AI agents, and the precise solutions.

| Conflict Area | Location A | Location B | Why It Confuses AI Agents | Top 1% Developer Solution |
| :--- | :--- | :--- | :--- | :--- |
| **Strict PHP Typing** | `SKILL.md` (Lines 49-56)<br>`architecture.md` (Lines 875-876)<br>*(Exempts pure-markup patterns from `declare(strict_types=1);`)* | `references/scaffold-tree.md` (Line 5)<br>*(Says "Every .php file ... must begin with declare(strict_types=1);")* | The agent gets stuck in a loop trying to decide whether to add strict types to HTML-only block patterns, resulting in linting smells or inconsistent headers. | **Standardize on exemption:** Block patterns that only contain static block comments/markup and a docblock header MUST NOT include `declare(strict_types=1);`. Update the scaffold tree to reflect this. |
| **theme.json Schema URL** | `SKILL.md` (Line 145)<br>`theme-json.md` (Line 30)<br>`api-allowlist.md` (Line 174)<br>*(Bans `trunk` schema as fabricated/unsafe, requires `/wp/7.0/`)* | `references/scaffold-tree.md` (Line 12)<br>`evals.json` (Line 7)<br>*(Instructs the agent to use `trunk` schema)* | The scaffold tree explicitly mandates using a schema URL that is banned by the allowlist and main developer rules. The agent will fail its own gates or output invalid URLs. | **Universal Schema Lock:** Globally replace all references to `trunk/theme.json` with the pinned, stable production schema: `https://schemas.wp.org/wp/7.0/theme.json`. |
| **JS File Scoping (index.js)** | `SKILL.md` (Line 140)<br>`architecture.md` (Line 1052)<br>*(Classifies `index.js` as non-reactive, traditional vanilla JS enqueued via conditional script queue)* | `references/scaffold-tree.md` (Line 36)<br>*(Classifies `index.js` as a Script Module: `index.js ← Optional, Script Module`)* | Script Modules are ES modules compiled/registered via `wp_register_script_module()` (like `view.js`). Labeling non-reactive vanilla JS as a Script Module causes enqueuing and compilation failures in `@wordpress/build`. | **Clarify Roles:** Standardize that `view.js` is the ONLY reactive Script Module. `index.js` is a non-reactive script enqueued via classic conditional asset queues, or compiled separately without reactive dependencies. |
| **Double Registration** | `SKILL.md` (Lines 74-79)<br>*(Bans registering the same template via theme.json customTemplates AND `register_block_template()`)* | `references/architecture.md` (Lines 71-74 & 104-111)<br>*(Physical landing page template exists in `templates/` AND is registered via PHP)* | The custom landing page conversion guide violates its own architecture gate by demonstrating a double-registration for `my-landing-page`, causing silent editor registration conflicts. | **Standardize on theme-native templates:** In a pure block theme, templates must be placed physically in `templates/` and registered via `theme.json` `customTemplates`. Remove the PHP template registration snippet from the theme-level conversion guide. |
| **Fabricated Icon API** | `api-allowlist.md` (Line 170)<br>`architecture.md` (Lines 480-492)<br>`html-conversion.md` (Lines 516-530) | WordPress 7.0 Core Specifications<br>*(Does not contain a public wp_register_icons API)* | The agent is instructed to use a fabricated filter `wp_register_icons` to register custom theme icons against `core/icon`. The enqueued code will fail silently because this registry is private/internal in WP 7.0 core. | **Banish Fabrications:** Delete all references to `wp_register_icons` from `SKILL.md`, `architecture.md`, `html-conversion.md`, and `api-allowlist.md`. Define standard pathways (inline SVGs or enqueued `.svg` images) for theme custom icons. |
| **Evaluation Criteria** | `evals.json` (Line 7)<br>*(Requires `trunk` schema URL)* | `evals.json` (Line 16)<br>*(Requires `wp/7.0` schema URL)* | The evaluation criteria contain a literal contradiction. The AI agent will fail compilation or verification because the expected output asks for both `trunk` and `wp/7.0` simultaneously. | **Refactor Evals:** Update `evals.json` expectation 7 to require the `/wp/7.0/` schema, ensuring absolute test parity. |

---

## 3. WordPress 7.0 Modernization & Fallback Elimination

The user is adopting this skill **exclusively after the WordPress 7.0 launch** and has strictly banned fallback mechanisms. Keeping old compatibilities creates complex code branches that lead to messy, buggy agent outputs.

### Action Items for Total 7.0 Standardization:
1. **Delete `references/compatibility-6.9.md` entirely.**
2. **Purge all references to "6.9", "earlier versions", or "fallbacks"** from `SKILL.md`, `architecture.md`, and `api-allowlist.md`.
3. **Enforce WordPress 7.0 Native APIs** with no alternative paths:
   - **Custom Blocks:** Always use PHP-only dynamic blocks with `'supports' => ['autoRegister' => true]`. No legacy `block.json` + `render.php` fallback.
   - **SVG Icons:** Since WP 7.0's `core/icon` block uses an internal/private registry (`WP_Icons_Registry`) with no public registration hooks, custom theme icons cannot be registered against it. Banish the fabricated `wp_register_icons` filter. Standardize on enqueuing SVG files via HTML `<img>` elements or outputting inline SVG code within dynamic blocks / PHP helpers.
   - **Breadcrumbs:** Always use `core/breadcrumbs` + `block_core_breadcrumbs_items` filter.
   - **Responsive Visibility:** Always use native CSS-based visual visibility (`metadata.blockVisibility.viewport`). Banish custom CSS breakpoint overrides (`display: none`).
   - **JS Reactivity:** Always use Script Modules (`wp_register_script_module()`) + Interactivity API (`watch()` / `data-wp-watch`).
   - **Button Styling:** Always use pseudo-element states (`:hover`, `:focus`, `:active`) directly in `theme.json`. Banish scoped CSS overrides for button states.

---

## 4. Surgical Code Corrections (For the Skill Maintainer Agent)

Below are the exact replacement chunks to be executed to resolve all contradictions and modernize the skill structure.

### Refactor 1: `references/scaffold-tree.md`
Remove the `trunk` schema contradiction, clarify the strict types pattern exemption, and fix the `index.js` label.

```diff
- 5: > **PHP 8.3 rule:** Every `.php` file created from these scaffolds must begin with `<?php` followed immediately by `declare(strict_types=1);`.
+ 5: > **PHP 8.3 rule:** Every `.php` file containing active PHP logic/functions must begin with `<?php` followed immediately by `declare(strict_types=1);`. Pure-markup pattern files in `patterns/*.php` (containing only the block comment docblock and HTML block markup with no functions/classes) are exempt.
 
- 12: ├── theme.json                          ← Settings & global styles (use "version": 3, $schema trunk)
+ 12: ├── theme.json                          ← Settings & global styles (use "version": 3, schema: https://schemas.wp.org/wp/7.0/theme.json)
 
- 36: │       └── index.js                    ← Optional, Script Module
+ 36: │       └── index.js                    ← Optional, non-reactive vanilla JS (classic conditional asset queue)
```

---

### Refactor 2: `references/architecture.md`
Remove the double-registration code snippet, align the strict types docblock rules, purge the custom icon registration code, and purge fallback warnings.

```diff
- 2: *Target: WordPress 7.0 (released 2026-05-20). This reference targets WP 7.0 only. There is no fallback path.*
+ 2: *Target: WordPress 7.0+ (released 2026-05-20). Strictly enforces WordPress 7.0 native APIs. Legacy fallbacks are completely banned.*
 
- 99: ### Template Registration API (PHP)
- 100: 
- 101: `register_block_template()` (introduced in 6.7) registers a template from PHP. This is useful for plugins or for themes that need templates to ship as code rather than as `.html` files inside `templates/`.
- 102: 
- 103: ```php
- 104: add_action( 'init', function(): void {
- 105:     register_block_template( '{{THEME_SLUG}}//my-landing-page', array(
- 106:         'title'       => __( 'My Landing Page', '{{TEXT_DOMAIN}}' ),
- 107:         'description' => __( 'A high-performance landing page template.', '{{TEXT_DOMAIN}}' ),
- 108:         'content'     => '<!-- wp:pattern {"slug":"{{THEME_SLUG}}/my-landing-page"} /-->',
- 109:         'post_types'  => array( 'page' ),
- 110:     ) );
- 111: } );
- 112: ```
- 113: 
- 114: The template name must be in the form `namespace//template_name` (two slashes). Supported `$args` keys are `title`, `description`, `content`, and `post_types`. See developer.wordpress.org/reference/functions/register_block_template/ for the full reference.
```

```diff
- 480: | Custom theme icons | Register via the `wp_register_icons` filter, then use `<!-- wp:icon {"icon":"{{THEME_SLUG}}/...\"} /-->`. The block is server-side rendered via the `/wp/v2/icons` REST endpoint. |
- 481: 
- 482: ```php
- 483: add_filter( 'wp_register_icons', function( array $icons ): array {
- 484:     $icons['{{THEME_SLUG}}/my-icon'] = array(
- 485:         'label'  => __( 'My Icon', '{{TEXT_DOMAIN}}' ),
- 486:         'src'    => get_stylesheet_directory_uri() . '/assets/icons/my-icon.svg',
- 487:         'width'  => 24,
- 488:         'height' => 24,
- 489:     );
- 490:     return $icons;
- 491: } );
- 492: ```
+ 480: | Custom theme icons | WordPress 7.0 does not support a public custom icon registration API. Do NOT attempt to register custom icons for `core/icon`. Custom SVGs must be output inside inline block markup or as enqueued asset templates. |
```

---

### Refactor 3: `references/html-conversion.md`
Purge the custom icon registration code from the conversion guidelines.

```diff
- 33: | `SVG Icon` | `core/icon` (WP 7.0) | Register the icon against the new Icon Registration API so it appears in the inserter. |
+ 33: | `SVG Icon` | Custom Block / Inline SVG | WP 7.0 core/icon registry is private. Render custom icons inline using raw SVGs or dynamic PHP-only blocks. |
```

```diff
- 516: ### Icon Conversion (WP 7.0)
- 517: 
- 518: WordPress 7.0 ships a `core/icon` block backed by an Icon Registration API and a REST endpoint at `/wp/v2/icons`. Register custom theme icons via the `wp_register_icons` filter, then reference them in markup:
- 519: 
- 520: ```php
- 521: add_filter( 'wp_register_icons', function( array $icons ): array {
- 522:     $icons['{{THEME_SLUG}}/warning'] = array(
- 523:         'label'  => __( 'Warning', '{{TEXT_DOMAIN}}' ),
- 524:         'src'    => get_stylesheet_directory_uri() . '/assets/icons/warning.svg',
- 525:         'width'  => 24,
- 526:         'height' => 24,
- 527:     );
- 528:     return $icons;
- 529: } );
- 530: ```
- 531: 
- 532: ```html
- 533: <!-- wp:icon {"icon":"{{THEME_SLUG}}/warning"} /-->
- 534: ```
+ 516: ### Icon Conversion (WP 7.0)
+ 517: 
+ 518: WordPress 7.0's core icon registry is internal/private. Do not attempt to use `core/icon` with unregistered slugs. Instead, render custom SVG icons via inline SVG markup in patterns or dynamic blocks, or enqueued SVG image files.
```

---

### Refactor 4: `references/api-allowlist.md`
Standardize the "Names that look real but are not" section to reflect that `wp_register_icons` is fabricated and custom registration is internal.

```diff
- 170: | `WP_Icons_Registry::get_instance()->register(...)` | Use the Icon Registration API documented at make.wordpress.org/core (the final 7.0 helper signature) or a PHP icon helper as fallback. |
+ 170: | `wp_register_icons` or `WP_Icons_Registry::get_instance()->register(...)` | Custom theme icons cannot be registered with the new core icon registry in 7.0 (it is internal/private). Enqueue SVGs via traditional PHP helpers or HTML inline markup. |
```

---

### Refactor 5: `evals/evals.json`
Surgically align the schema requirement in the evaluations block.

```diff
- 7: ...theme.json uses `\"version\": 3` and the trunk schema URL.
+ 7: ...theme.json uses `\"version\": 3` and the pinned 7.0 schema URL (https://schemas.wp.org/wp/7.0/theme.json).
```

---

### Refactor 6: File Deletion Action
Instruct the skill maintenance pipeline to execute the following deletion:
```bash
rm references/compatibility-6.9.md
```
Remove all mentions of `compatibility-6.9.md` from the references table in `SKILL.md` (Line 438).

---

## 5. Verification Checklist for the Skill Maintainer

When testing and verifying updates to this block-theme developer skill, ensure that:
- [ ] No generated block theme codebase contains `<!-- wp:html -->` comment blocks.
- [ ] All dynamic blocks utilize the native WP 7.0 `'autoRegister' => true` registration schema inside PHP.
- [ ] All custom templates are registered in `theme.json` under `customTemplates` with physical template HTML files in the `templates/` folder (no PHP double-registrations).
- [ ] Every `theme.json` file declares exactly `"version": 3` and the pinned `"https://schemas.wp.org/wp/7.0/theme.json"` schema.
- [ ] Pure-markup block patterns are 100% free of `declare(strict_types=1);` headers, while functions.php and dynamic blocks are strictly typed (PHP 8.3).
- [ ] Custom theme icons are rendered using clean SVG helpers or inline HTML/PHP templates (no fabricated `wp_register_icons` filter is present).
