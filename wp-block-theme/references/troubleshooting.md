# Troubleshooting — symptoms, causes and fixes

**Load this file when** the user reports something broken or not showing: a template missing from the dropdown, CSS not loading, the editor not matching the frontend, old content after a file change, a template part rendering empty, a script never loading, or a white screen after deploying.

For DOM-level diagnostics, use the **Diagnostic Console Snippet** in `architecture.md`.

## Contents
- Conversion pitfalls (HTML → block theme)
- Templates, patterns and template parts
- CSS, JS and the editor
- Blocks and visibility
- PHP and versions

## Conversion pitfalls (HTML → block theme)

| Problem | Symptom | Fix |
|---|---|---|
| "Black box" templates | Can't edit colors/text in the Site Editor | Rebuild custom sections using Core Blocks; only fall back to a dynamic block (`register_block_type()` + `render_callback`) when the design truly cannot map to core. |
| Raw HTML stripped | Content disappears in the editor | Non-block HTML was pasted into a template. Register a dynamic block (`register_block_type()` + `render_callback`) and emit the HTML from the callback. |
| CSS bleeds into editor UI | Styles affect menus/toolbars | Scope rules strictly to the `.is-style-{slug}` variation class. |
| Template not accessible | A11y warnings/fail | Use semantic tags (nav, main) and ARIA labels. |
| Client breaks layout | Sections deleted accidentally | Apply `"lock": {"move": true, "remove": true}` to structural blocks. |
| Animations fire in a preview iframe | Jarring experience | Gate the script on `window.frameElement`. |
| theme.json colors missing | Parent palette deleted | The child `theme.json` must include the parent palette entries before appending its own. |

## Templates, patterns and template parts

| Symptom | Cause | Fix |
|---|---|---|
| Template not in Page Attributes dropdown | Not registered in `theme.json` `customTemplates` | Add the entry; check the `name` matches the filename (without `.html`). |
| Old content persists after file change | A customised copy of the template or part is stored in the database | Use the template's **Reset** action in the Site Editor. Its revisions can restore earlier edits if the user wants them back. |
| theme.json style changes ignored | The user's Global Styles override the theme's | In the Site Editor's Styles panel use **Reset styles**. That discards the user's style customisations, so confirm first. |
| Pattern images broken | Hardcoded path, or a parent-theme pattern using the child theme's directory | Use `get_theme_file_uri( 'assets/images/…' )` in the PHP pattern — it returns the child theme's copy when one exists and the parent's otherwise. |
| Block validation error | Nesting issue or stray HTML | Validate markup; every chunk of raw HTML lives inside a registered dynamic block, not loose in the template. |
| **Template part renders as empty `<div class="wp-block-template-part">`** | `wp:template-part` placed inside a dynamic block's `render_callback` output | Move `wp:template-part` comments to the root of the `.html` template, outside any dynamic block. |
| **Template part exists in DOM but content is empty** | Pattern is missing or the slug has a typo | Check the `slug` inside the `wp:pattern` comment matches the PHP registration exactly. |
| **PHP functions not running in template part** | PHP put directly in an `.html` file | `.html` files cannot run PHP — put PHP in a `.php` pattern and call it via `<!-- wp:pattern {"slug":"..."} /-->`. |

## CSS, JS and the editor

| Symptom | Cause | Fix |
|---|---|---|
| CSS not loading on frontend | Slug missing from the pattern-asset registry, or it does not match the folder / class | The registry key, the `patterns/{slug}/` folder and the `is-style-{slug}` class on the outermost block must be identical. Confirm `functions.php` requires `inc/pattern-assets.php`. |
| Editor doesn't match frontend | Stylesheet enqueued for the frontend only, or the slug is missing from the registry | Pattern CSS must go through the registry — the loader also enqueues it on `enqueue_block_assets`, which is what reaches the editor canvas. |
| **Template part CSS not loading** | Outermost block missing its style class, or slug not in the registry | The backing pattern's root block needs `is-style-{slug}` and `{slug}` must be in the pattern-asset registry. |
| **Pattern script never loads** | `'script' => true` / `'module' => true` missing from the registry entry, or the file is not `patterns/{slug}/index.js` / `view.js` | Fix the registry entry. `register_block_style()` cannot attach a script. |
| **Post editor canvas is not iframed (7.0)** | A block with `apiVersion` below 3 is in the post | 7.0 drops the iframe when such a block is present. Upgrade the block to API version 3. On 7.1+ the editor is always iframed. |

## Blocks and visibility

| Symptom | Cause | Fix |
|---|---|---|
| Block never renders | `metadata.blockVisibility` is `false` | Remove the attribute to show the block. |
| Viewport hiding has no effect | `viewport` written as an array (`["desktop"]`) | Use the object form: `{"viewport": {"mobile": false, "tablet": false}}`. |
| **`blockVisibility` PHP type error** | Treating `metadata.blockVisibility` as always an array — it can also be the boolean `false` | Check `false === $visibility` first, then `is_array( $visibility )`. The block **support** key is `visibility`; the **metadata attribute** is `blockVisibility`. |
| **`core/icon` renders nothing** | Icon name is not registered | On 7.0 only built-in `core/{name}` icons exist. Custom icons need 7.1+ (`core-7.1-apis.md`). |

## PHP and versions

| Symptom | Cause | Fix |
|---|---|---|
| **White screen / parse error after deploying** | Code uses syntax newer than the server's PHP (e.g. property hooks on 8.3) | Establish the site's PHP version and rewrite with `php-compatibility.md`; declare `Requires PHP` in `style.css`. |
