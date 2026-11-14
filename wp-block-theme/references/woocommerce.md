# WooCommerce Block Theme Development

This reference covers everything a block theme needs to integrate cleanly with WooCommerce: the block component registry, blockified template overrides, CSS architecture (BEM + container queries), the Cart/Checkout JS filter registry, conditional tags, the `wc_get_logger()` framework, and the core function/namespace map.

## 1. When to Load This File

Load this file **before writing any code** when the user asks to:

- Override the WooCommerce Shop, Single Product, Cart, Checkout, Order Confirmation, Search, or Coming Soon template.
- Compose, override, or customise any `woocommerce/*` block (Product Image, Add to Cart Button, Price, Rating, Sale Badge, Mini-Cart, Accordion, etc.).
- Style the Cart, Checkout, or Mini-Cart blocks.
- Extend Cart/Checkout JS via `registerCheckoutFilters()`.
- Use WooCommerce conditional tags (`is_shop()`, `is_product()`, `is_cart()`, etc.) in theme PHP.
- Call `wc_get_logger()` from theme or plugin code.
- Touch anything in the `Automattic\WooCommerce` namespace.

If the work is generic WP FSE with no WooCommerce surface, stay in the main `SKILL.md` and skip this file.

This file is the **rendering surface** (blocks, templates, CSS, conditional tags, logging). For deeper WooCommerce work, load the focused companion instead of guessing:

- **`woocommerce-checkout-fields.md`** — adding/modifying/validating custom checkout fields (`woocommerce_register_additional_checkout_field`, sanitize/validate, JSON-Schema conditional logic, meta-key prefixes, stale-data trap).
- **`woocommerce-checkout-lifecycle.md`** — client-side checkout JS: data stores, status machine, observers (`onCheckoutValidation` / `onPaymentSetup` / `onCheckoutSuccess`), SlotFill, native DOM events. (The static value filters in §6 below stay here; the *event* layer lives there.)
- **`woocommerce-performance.md`** — store caching/cookie exclusions, DB hygiene, GZIP, asset optimization, Core Web Vitals.
- **`woocommerce-standards.md`** — safe customization (child themes/hooks/snippets), PHP/JS naming, isolation mandates, Webpack dependency extraction, `is_ssl()` behind load balancers, QA.

## 2. Pre-Write Gates (WooCommerce-Specific)

These five gates apply **on top of** the five gates in `SKILL.md → How to approach a request → Pre-write gates`. All must pass before writing code.

- **Block name gate** — every `woocommerce/*` block name used in template markup must appear in §3 below or be confirmed in the plugin's own block metadata (§3 is a subset, not the full list). Common AI failure mode: inventing names like `woocommerce/cart-button`, `woocommerce/checkout-form`, `woocommerce/product-add-to-cart`. The real surface is composed of smaller blocks (e.g. Cart is a tree of nested blocks; the "Add to Cart Button" is `woocommerce/product-button`). When unsure, grep the `"name"` field of the `block.json` files under `wp-content/plugins/woocommerce/assets/client/blocks/` and the `$block_name` properties under `wp-content/plugins/woocommerce/src/Blocks/BlockTypes/` rather than guessing.
- **Ancestor gate** — product element blocks (price, image, button, rating, sale-badge, sku, stock, summary, etc.) need a product context. They declare an `ancestor` list in `block.json` (e.g. `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template`), which restricts where the editor allows them, and on the front end they look the product up from the `postId` block context and render nothing when no product can be resolved. Place them inside a declared ancestor. The Single Product template is the exception for price, rating, sale-badge, stock and summary: WooCommerce registers those five without the ancestor restriction there, and its own default template uses `woocommerce/product-price` and `woocommerce/product-rating` with no wrapper. Always check the Ancestor column in §3.
- **Internal privacy gate** — the HTML structure *inside* a WooCommerce block (nesting of internal `<div>`s, the structure of `.wc-block-cart-item__row`, etc.) is private and changes between releases. CSS selectors that depend on that structure, or on one block being a descendant of another, will break on update. Prefer global styles (`theme.json`) and **block style variations** (`register_block_style()` — see main `SKILL.md`); where CSS is unavoidable, target only the documented class prefixes in §5.1.
- **Conditional tag timing gate** — `is_shop()`, `is_product()`, `is_cart()`, etc. are only valid after the `posts_selection` action fires. The earliest reliable hook is `wp`. **Never call them at global scope in `functions.php`** — they return false-y noise and silently break logic. A theme stays active when WooCommerce is deactivated, and then these functions do not exist: guard every call to a WooCommerce function from theme code with `function_exists()`, or run it only from a WooCommerce hook. See §7 for the safe pattern.
- **Placement gate** — code that changes what the store *does* (an added or required checkout field, validation, anything written to an order or a customer) goes in a **plugin** by default, because it has to keep working when the theme is switched; say so when you deliver it. A few lines of PHP that need no files of their own may instead be a snippet for a snippets plugin (`references/woocommerce-standards.md → §2.3`). Cosmetic changes — button labels, price formats, a notice, styling — may live in the theme. For store behaviour this gate overrides the child-theme default, and working inside a theme project is not a request to put the code there: only when the user names the theme (or its `functions.php`) as the place, write it there and state that it stops working when the theme changes. In a plugin, `{{THEME_PREFIX}}`, `{{THEME_SLUG}}` and `{{TEXT_DOMAIN}}` in the WooCommerce examples stand for the plugin's own prefix, slug and text domain; its layout is `references/scaffold-tree.md → Companion plugin`.

## 3. Block Component Registry

### 3.1 Product Elements

These blocks need a product context: nest them inside one of their declared Ancestors (the §2 Ancestor gate covers the Single Product template exception). Category for all is `woocommerce-product-elements`.

| Block (Internal Name) | Ancestor(s) | Key Supports | Key Attributes |
|---|---|---|---|
| `woocommerce/product-button` (Add to Cart) | `woocommerce/all-products`, `woocommerce/single-product`, `core/post-template`, `woocommerce/product-template` | `align (full, wide)`, `color (background, text)`, `email`, `interactivity`, `spacing`, `typography`, `html: false` | `productId`, `textAlign`, `width` |
| `woocommerce/product-image` | `woocommerce/all-products`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `dimensions (aspectRatio)`, `email`, `interactivity (clientNavigation)`, `spacing`, `typography (fontSize)`, `html: false` | `aspectRatio`, `height`, `imageSizing`, `productId`, `saleBadgeAlign`, `scale`, `showProductLink`, `showSaleBadge`, `width` |
| `woocommerce/product-price` | `woocommerce/all-products`, `woocommerce/featured-product`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `color`, `email`, `interactivity`, `spacing`, `typography`, `html: false` | `productId`, `textAlign` |
| `woocommerce/product-rating` | `woocommerce/all-products`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `color (text)`, `interactivity (clientNavigation)`, `spacing`, `typography (fontSize)` | `productId`, `textAlign` |
| `woocommerce/product-rating-counter` | `woocommerce/single-product` | `color (link)`, `interactivity (clientNavigation)`, `spacing`, `typography`, `inserter: false` | `productId`, `textAlign` |
| `woocommerce/product-rating-stars` | `woocommerce/single-product` | `color (text)`, `interactivity (clientNavigation)`, `spacing`, `typography`, `inserter: false` | `productId`, `textAlign` |
| `woocommerce/product-average-rating` *(Beta)* | `woocommerce/single-product` | `color`, `interactivity (clientNavigation)`, `spacing`, `typography` | `textAlign` |
| `woocommerce/product-sale-badge` | `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template`, `woocommerce/product-gallery` | `align`, `color (background, gradients, text)`, `email`, `interactivity (clientNavigation)`, `spacing (margin)`, `typography`, `html: false` | `badgeContent`, `prefix`, `productId`, `saleText`, `suffix` |
| `woocommerce/product-sku` | `woocommerce/product-meta`, `woocommerce/all-products`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `color`, `interactivity (clientNavigation)`, `spacing`, `typography`, `html: false` | `isDescendantOfAllProducts`, `prefix`, `productId`, `showProductSelector`, `suffix` |
| `woocommerce/product-stock-indicator` | `woocommerce/all-products`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `color`, `interactivity`, `spacing`, `typography`, `html: false` | `isDescendantOfAllProducts` |
| `woocommerce/product-summary` | `woocommerce/all-products`, `woocommerce/featured-product`, `woocommerce/single-product`, `woocommerce/product-template`, `core/post-template` | `color (background, link, text)`, `interactivity (clientNavigation)`, `spacing`, `typography (fontSize, lineHeight, textAlign)` | `isDescendantOfAllProducts`, `isDescendentOfQueryLoop`, `isDescendentOfSingleProductBlock`, `isDescendentOfSingleProductTemplate`, `linkText`, `productId`, `showDescriptionIfEmpty`, `showLink`, `summaryLength` |
| `woocommerce/product-title` | `woocommerce/all-products` | `color (background, gradients, text)`, `spacing (margin)`, `typography (fontSize, lineHeight)`, `html: false` | `align`, `headingLevel`, `linkTarget`, `productId`, `showProductLink` |

### 3.2 Composite & Layout Blocks

| Block (Internal Name) | Category | Notes |
|---|---|---|
| `woocommerce/product-image-gallery` | `woocommerce-product-elements` | Supports `align`, `interactivity (clientNavigation)`, `multiple: false`. |
| `woocommerce/add-to-cart-form` (Add to Cart with Options) | `woocommerce-product-elements` | Supports `interactivity`; attribute `quantitySelectorStyle`. Declares no `ancestor` in `block.json`, but uses the `postId` context and renders nothing when that does not resolve to a product. In the editor WooCommerce registers it with `woocommerce/single-product` as its ancestor, except in the Single Product template. |
| `woocommerce/product-meta` | `woocommerce-product-elements` | Supports `align`, `interactivity (clientNavigation)`, `reusable: false`. Acts as an ancestor for `woocommerce/product-sku`. |
| `woocommerce/related-products` | `woocommerce` | Supports `align`, `interactivity (clientNavigation)`, `inserter: false`, `reusable: false`. |

### 3.3 Accordion

| Block | Parent | Supports | Attributes |
|---|---|---|---|
| `woocommerce/accordion-group` | — (top-level) | `align (full, wide)`, `background`, `color`, `interactivity`, `layout`, `shadow`, `spacing`, `html: false` | `allowedBlocks`, `autoclose`, `iconPosition` |
| `woocommerce/accordion-item` | `woocommerce/accordion-group` | `align (full, wide)`, `color`, `interactivity`, `layout`, `shadow`, `spacing` | `openByDefault` |
| `woocommerce/accordion-header` | `woocommerce/accordion-item` | `anchor`, `border`, `color`, `interactivity`, `layout`, `shadow`, `spacing`, `typography` | `icon`, `iconPosition`, `level`, `levelOptions`, `openByDefault`, `textAlignment`, `title` |
| `woocommerce/accordion-panel` | `woocommerce/accordion-item` | `border`, `color`, `interactivity`, `layout`, `shadow`, `spacing`, `typography` | `allowedBlocks`, `isSelected`, `openByDefault`, `templateLock` |

### 3.4 Mini-Cart Nesting Tree

The drawer contents are not inner blocks of `woocommerce/mini-cart`: they live in the `mini-cart` template part, whose root is `woocommerce/mini-cart-contents`. WooCommerce loads that template part from the site's saved copy first, then the theme, then its own default. Each child below declares the block above it as its `parent` in `block.json`:

```
woocommerce/mini-cart-contents            (root of the mini-cart template part)
├── woocommerce/filled-mini-cart-contents-block
│   ├── woocommerce/mini-cart-title-block
│   │   ├── woocommerce/mini-cart-title-label-block
│   │   └── woocommerce/mini-cart-title-items-counter-block
│   ├── woocommerce/mini-cart-items-block
│   │   └── woocommerce/mini-cart-products-table-block
│   └── woocommerce/mini-cart-footer-block
│       ├── woocommerce/mini-cart-cart-button-block
│       └── woocommerce/mini-cart-checkout-button-block
└── woocommerce/empty-mini-cart-contents-block
    └── woocommerce/mini-cart-shopping-button-block
```

### 3.5 Order Confirmation Nesting Tree

As composed in WooCommerce's default `order-confirmation.html` (these blocks declare no `parent` in `block.json`):

```
woocommerce/order-confirmation-status
woocommerce/order-confirmation-summary
woocommerce/order-confirmation-totals-wrapper
└── woocommerce/order-confirmation-totals
woocommerce/order-confirmation-downloads-wrapper
└── woocommerce/order-confirmation-downloads
woocommerce/order-confirmation-shipping-wrapper
└── woocommerce/order-confirmation-shipping-address
woocommerce/order-confirmation-billing-wrapper
└── woocommerce/order-confirmation-billing-address
woocommerce/order-confirmation-additional-fields-wrapper
└── woocommerce/order-confirmation-additional-fields
woocommerce/order-confirmation-additional-information
```

The wrappers exist to carry layout/spacing; the inner blocks own the data binding. Override at the wrapper level for layout work, the inner block level for data work.

## 4. Blockified Template System

WooCommerce ships block-native HTML templates that themes can override using the same mechanism as core WordPress (`templates/{name}.html` in the theme directory wins).

### 4.1 Overridable Templates

| Template | Purpose |
|---|---|
| `single-product.html` | Single product detail page |
| `archive-product.html` | Shop / product archive |
| `taxonomy-product_attribute.html` | Product attribute archive (e.g. `/color/red`) |
| `product-search-results.html` | Search results scoped to products |
| `page-cart.html` | Cart page |
| `page-checkout.html` | Checkout page |
| `order-confirmation.html` | Thank-you / order received page |
| `coming-soon.html` | Coming Soon page |

### 4.2 Architecture Rules

- **Override at the template level only.** The HTML structure *inside* a WooCommerce block is private (see §2 Internal privacy gate). Themes recompose templates from documented blocks; they do not patch internals.
- **Same precedence as core.** Drop a same-named `.html` file in the theme's `templates/` directory and it wins over the WooCommerce default — no registration needed.
- **Recommended pattern.** Wrap each overridden template's body in a master pattern at `patterns/{template-slug}.php`. The master holds only a flat list of `<!-- wp:pattern -->` calls; every WooCommerce block lives in a sub-pattern the master calls. The master-pattern + sub-pattern rules in `SKILL.md` Sections 0 and 6 apply unchanged. Example:

  ```html
  <!-- templates/single-product.html -->
  <!-- wp:template-part {"slug":"header","tagName":"header","area":"header"} /-->
  <!-- wp:pattern {"slug":"{{THEME_SLUG}}/single-product"} /-->
  <!-- wp:template-part {"slug":"footer","tagName":"footer","area":"footer"} /-->
  ```

  `patterns/single-product.php` (the master) only calls sub-patterns, for example `{{THEME_SLUG}}/single-product-summary`. That sub-pattern (`patterns/single-product-summary.php`) carries the WooCommerce blocks inside its own outermost block. Use the blocks WooCommerce's default Single Product template uses — `woocommerce/product-image-gallery`, `woocommerce/product-rating`, `woocommerce/product-price`, `woocommerce/add-to-cart-form` and `woocommerce/product-meta` (wrapping `woocommerce/product-sku`) — with no `woocommerce/single-product` wrapper. `woocommerce/single-product` is a different tool: it displays one chosen product (its `productId` attribute) on other pages.

- **Ancestor gate still applies.** Of the product elements that declare an `ancestor` (§3.1), only price, rating, sale-badge, stock and summary are exempt from it in the Single Product template (§2). Every other §3.1 block, and any product element on another template or page, goes inside one of its declared ancestors (e.g. `woocommerce/single-product` with a `productId`, or `woocommerce/product-template`).

## 5. CSS Styling Architecture

### 5.1 BEM Naming and Class Prefix Priority

| Prefix | Meaning | Use in theme CSS? |
|---|---|---|
| `.wc-block-*` | Block-specific class (deprecated when a `components-*` equivalent exists) | Only if no `components-*` equivalent exists |
| `.wc-block-components-*` | Reusable component class — **preferred** | Yes — target this when both prefixes are present |
| `.price`, `.star-rating`, `.button` (unprefixed) | Legacy classes | **Forbidden in new code** — they collide with the editor chrome and third-party plugins |

**Conflict rule:** if an element carries both `.wc-block-foo` and `.wc-block-components-foo`, write the rule against `.wc-block-components-foo`. The `.wc-block-*` variant is deprecated and may be removed.

### 5.2 Container Queries

The Cart and Checkout block wrappers are CSS containers (`container-type: inline-size`), so their contents respond to the wrapper's width through `@container` queries rather than viewport media queries. This lets the same Cart render at full-page width on Cart Page and at narrow width in a sidebar widget without theme-side breakpoint hacks.

Mapping table (legacy class → the container query WooCommerce's own styles use for the same tier):

| Container width | Legacy class | WooCommerce's `@container` query |
|---|---|---|
| > 920px | `.is-large` | `@container (min-width: 920px)` |
| 521–920px | `.is-medium` | `@container (min-width: 520px) and (max-width: 919px)` |
| 401–520px | `.is-small` | `@container (min-width: 400px) and (max-width: 519px)` |
| ≤ 400px | `.is-mobile` | `@container (max-width: 399px)` |

**Critical constraint:** do **not** define `container-type` on an intermediate wrapper between the WooCommerce block root and your target element. A second `container-type` resets the query context and the rule will silently never match. If you need a new container query inside a Cart/Checkout block, accept that you cannot — restructure to query against the same root.

### 5.3 Specificity and Semantic Elements

- **Resets.** WooCommerce applies CSS resets to semantic elements when they're used as functional UI (e.g. `<a>` styled as a button via `.wc-block-components-button`). Theme CSS should win with the lowest specificity that does the job. Aggressive `!important` chains override functional resets and break the block.
- **`[hidden]` attribute.** WooCommerce blocks use the HTML `hidden` attribute to hide elements from the screen and from assistive technology. This needs attention only when the theme's own CSS sets `display` on generic elements (e.g. `div { display: block; }`). If a stylesheet in the theme has such a rule, put the matching `hidden` rule in that same stylesheet, directly after it:

  ```css
  div[hidden] { display: none; }
  ```

## 6. Cart & Checkout Filter Registry

The JS extension API for Cart and Checkout. Lives in `@woocommerce/blocks-checkout` (available at runtime as the `wc.blocksCheckout` global — see §6.4) and is invoked via `registerCheckoutFilters( 'namespace', { filterName: callback } )`. Filter callbacks receive `( value, extensions, args )` — the current value, the extensions bag, and an args object (for line-item filters: `cart`, `cartItem` and a `context` of `cart` or `summary`) — and return the modified value.

### 6.1 Filter Catalogue (grouped by target area)

| Area | Available filters |
|---|---|
| **Cart line items** | `cartItemClass`, `cartItemPrice`, `itemName`, `saleBadgePriceFormat`, `showRemoveItemLink`, `subtotalPriceFormat` |
| **Order summary items** (Checkout sidebar) | `cartItemClass`, `cartItemPrice`, `cartItemScreenReaderPrice`, `itemName`, `subtotalPriceFormat` |
| **Totals footer** | `totalLabel`, `totalValue` |
| **Checkout buttons** | `proceedToCheckoutButtonLabel`, `proceedToCheckoutButtonLink`, `placeOrderButtonLabel` |
| **Coupons** | `coupons`, `showApplyCouponNotice`, `showRemoveCouponNotice` |
| **Registry extensibility** | `additionalCartCheckoutInnerBlockTypes` |

### 6.2 Protocol

- **Combinable.** A single `registerCheckoutFilters()` call may set as many filters as needed — group them by extension namespace.
- **One call per namespace.** A second `registerCheckoutFilters()` call with the same namespace **replaces** the first; it does not merge, and nothing is reported. Put every filter registered under a namespace in one call.
- **Required placeholders.** Price-format filters (`cartItemPrice`, `subtotalPriceFormat`, `saleBadgePriceFormat`, `totalValue`) must return a string that contains `<price/>`. `cartItemScreenReaderPrice` (the order-summary text read to assistive technology) must contain all three of `<quantity/>`, `<productName/>` and `<price/>`.
- **Debugging.** A filter that returns a different type than it received, or a string missing a required placeholder, raises an error: it is thrown (and shown on screen) for logged-in administrators and written to the browser console for everyone else, and the rejected value is not applied. Always check the console before guessing.

### 6.3 Canonical Example

The API shape is identical across all filters — one example covers the whole surface:

```js
const { registerCheckoutFilters } = window.wc.blocksCheckout;
const { __ } = window.wp.i18n;

const namespace = '{{THEME_SLUG}}';

registerCheckoutFilters( namespace, {
	cartItemPrice: ( defaultValue, extensions, args ) => {
		if ( args?.cartItem?.totals?.line_total === '0' ) {
			/* translators: <price/> is a placeholder and must be kept as is. */
			return __( '<price/> (free)', '{{TEXT_DOMAIN}}' );
		}
		return defaultValue;
	},
	cartItemScreenReaderPrice: ( defaultValue, extensions, args ) => {
		if ( args?.cartItem?.totals?.line_total === '0' ) {
			/* translators: <quantity/>, <productName/> and <price/> are placeholders and must be kept as is. */
			return __( 'Total price for <quantity/> <productName/>: <price/> (free)', '{{TEXT_DOMAIN}}' );
		}
		return defaultValue;
	},
	proceedToCheckoutButtonLabel: () => __( 'Continue to secure checkout', '{{TEXT_DOMAIN}}' ),
} );
```

### 6.4 Loading the Script

Filters (§6.3), observers and fills (`references/woocommerce-checkout-lifecycle.md`) are plain scripts that read WooCommerce's globals. WooCommerce prescribes what such a script depends on and how it is loaded. The file locations are this skill's:

| File | Holds |
|---|---|
| `assets/js/checkout.js` | The script: every filter, observer and fill |
| `inc/woocommerce.php` | The registration callback. Required from `functions.php` with `require_once __DIR__ . '/inc/woocommerce.php';` |
| `inc/class-{{THEME_SLUG}}-checkout-integration.php` | The integration class. Required **only** by that callback, never from `functions.php` |

The three files live wherever the Placement gate (§2) puts the code: cosmetic filters and fills in the theme, validation and anything that touches order data in the plugin. A site that has both has the set twice, each with its own script handle, class name and integration name.

**Dependencies.** `registerCheckoutFilters` is provided by WooCommerce's `wc-blocks-checkout` script, which exposes the package as the `wc.blocksCheckout` global ("Package components can be accessed via the `wc` global"). Put every script whose global the file reads in the dependency array — `wc-blocks-checkout`, plus `wp-i18n` when it translates strings; the lifecycle file names the handles for observers and fills — and read the API from the global, as §6.3 does. A build that uses the Dependency Extraction Webpack Plugin may write `import { registerCheckoutFilters } from '@woocommerce/blocks-checkout'` instead (`references/woocommerce-standards.md → §5.1`).

**One file, one function scope.** Classic scripts share a single global scope: a top-level `const` is a `SyntaxError` in any later classic script that declares the same name, and that later script does not run at all. Wrap the whole file in a function, declare each name once, and make a single `registerCheckoutFilters()` call (§6.2). The examples in §6.3 and in the lifecycle file are the *body* of this wrapper:

```js
( () => {
	const { registerCheckoutFilters } = window.wc.blocksCheckout;
	const { __ } = window.wp.i18n;

	registerCheckoutFilters( '{{THEME_SLUG}}', {
		placeOrderButtonLabel: () => __( 'Pay securely', '{{TEXT_DOMAIN}}' ),
	} );
} )();
```

**Loading.** WooCommerce's mechanism is an *integration*: a class that implements `Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface`, handed to the registry that WooCommerce passes to the `woocommerce_blocks_cart_block_registration` and `woocommerce_blocks_checkout_block_registration` actions. Each block has its own registry, so the callback is hooked to both; add `woocommerce_blocks_mini-cart_block_registration` when the script also serves the Mini-Cart.

In both files, `My_Theme` stands for the slug written as `Capitalized_Words` (`my-theme` → `My_Theme`). Replace it as you replace the placeholders — two themes or plugins that keep the same class name are a fatal error. The file name follows from the class name (`references/php-coding-standards.md → §9`).

`inc/woocommerce.php`:

```php
<?php
declare(strict_types=1);

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the checkout script with a WooCommerce block.
 *
 * The class file is required here and nowhere else: the class implements a WooCommerce
 * interface, so loading it while WooCommerce is inactive is a fatal error. These actions
 * fire only when WooCommerce is running.
 */
function {{THEME_PREFIX}}_register_checkout_integration( IntegrationRegistry $integration_registry ): void {
	require_once __DIR__ . '/class-{{THEME_SLUG}}-checkout-integration.php';

	$integration_registry->register( new My_Theme_Checkout_Integration() );
}
add_action( 'woocommerce_blocks_cart_block_registration', '{{THEME_PREFIX}}_register_checkout_integration' );
add_action( 'woocommerce_blocks_checkout_block_registration', '{{THEME_PREFIX}}_register_checkout_integration' );
```

`inc/class-{{THEME_SLUG}}-checkout-integration.php`:

```php
<?php
declare(strict_types=1);

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads assets/js/checkout.js wherever the Cart or Checkout block is used.
 */
final class My_Theme_Checkout_Integration implements IntegrationInterface {
	private const HANDLE = '{{THEME_SLUG}}-checkout';

	/**
	 * Unique name of the integration.
	 */
	public function get_name(): string {
		return '{{THEME_SLUG}}';
	}

	/**
	 * Registers the script. WooCommerce enqueues it together with the block.
	 */
	public function initialize(): void {
		wp_register_script(
			self::HANDLE,
			get_theme_file_uri( 'assets/js/checkout.js' ),
			array( 'wc-blocks-checkout', 'wp-i18n' ),
			wp_get_theme( '{{THEME_SLUG}}' )->get( 'Version' ),
			array( 'in_footer' => true )
		);
		wp_set_script_translations( self::HANDLE, '{{TEXT_DOMAIN}}' );
	}

	/**
	 * Script handles for the frontend.
	 *
	 * @return string[]
	 */
	public function get_script_handles(): array {
		return array( self::HANDLE );
	}

	/**
	 * Script handles for the editor, so its Cart and Checkout preview matches the frontend.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles(): array {
		return array( self::HANDLE );
	}

	/**
	 * Data handed to the script. Empty when there is none.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		return array();
	}
}
```

- **All five methods are required.** They are the interface; a class that leaves one out is a fatal error.
- **`initialize()` only registers.** It runs whenever WooCommerce registers its blocks — on pages without a Cart or Checkout block too — and once for each block the integration is registered with. For the Cart and Checkout blocks WooCommerce adds the handles from `get_script_handles()` to the dependencies of the block's own frontend script, which it registers and enqueues when the block renders; the Mini-Cart block enqueues them directly. Either way the file loads on pages that contain the block.
- **Translations.** Keep the `wp_set_script_translations()` call whenever the script uses `wp.i18n`; drop it, and the `wp-i18n` dependency, when it does not.
- **In a plugin.** Three things change. The main plugin file requires `inc/woocommerce.php`, because a plugin has no `functions.php`. The script URL is `plugins_url( 'assets/js/checkout.js', dirname( __DIR__ ) . '/{{THEME_SLUG}}.php' )`: the second argument is the path of the main plugin file, and the URL is resolved against that file's folder. The version is the plugin's own version string, never `wp_get_theme( … )->get( 'Version' )`, which is `false` for a plugin and makes WordPress fall back to its own version number.
- **Script data.** Whatever `get_script_data()` returns reaches the script as the setting `{name}_data`, where `{name}` is the value of `get_name()`. Read it with `getSetting()` from the `wc.wcSettings` global and add `wc-settings` to the script's dependencies.

## 7. Conditional Tags

### 7.1 Catalogue

| Tag | True when… |
|---|---|
| `is_woocommerce()` | On the shop archive, a product taxonomy archive, or a single product (**false** on Cart, Checkout and My Account) |
| `is_shop()` | On the product archive (Shop page) |
| `is_product()` | On a single product detail page |
| `is_product_category( $slug )` | On a product category archive |
| `is_cart()` | On the Cart page |
| `is_checkout()` | On the Checkout page |
| `is_wc_endpoint_url( $endpoint )` | On a specific WC endpoint (e.g. `order-pay`, `order-received`) |
| `is_ajax()` | Inside an AJAX request |

### 7.2 Timing Constraint

Conditional tags are only valid **after** the `posts_selection` action. The earliest reliable hook is `wp`. Calling them at global scope in `functions.php` returns false-y noise and silently breaks logic — this is the single most common WooCommerce theme bug.

**Wrong** (global scope — `is_product()` is always false here):

```php
// functions.php — DO NOT DO THIS
if ( is_product() ) {
	add_filter( 'body_class', '{{THEME_PREFIX}}_product_body_class' );
}
```

**Right** (hooked into `wp`, and guarded for a site where WooCommerce has been deactivated):

```php
/**
 * Adds a body class on single product pages. Hooked to `wp`, where conditional tags are valid.
 */
function {{THEME_PREFIX}}_maybe_add_product_body_class(): void {
	if ( ! function_exists( 'is_product' ) ) {
		return; // WooCommerce is not active.
	}

	if ( is_product() ) {
		add_filter( 'body_class', '{{THEME_PREFIX}}_product_body_class' );
	}
}
add_action( 'wp', '{{THEME_PREFIX}}_maybe_add_product_body_class' );

/**
 * Appends the has-product class.
 */
function {{THEME_PREFIX}}_product_body_class( array $classes ): array {
	$classes[] = 'has-product';

	return $classes;
}
```

## 8. Logging and Debugging

WooCommerce ships a structured logger accessible via `wc_get_logger()`. Use it for runtime diagnostics — never `error_log()` or `print_r()` in production paths.

### 8.1 Canonical Pattern

```php
$logger  = wc_get_logger();
$context = array(
	'source'    => '{{TEXT_DOMAIN}}',
	'backtrace' => true, // include stack trace in the log entry
);

$logger->error( 'Payment gateway returned non-200 status while finalising order.', $context );
```

In theme code, run this from a WooCommerce hook or after a `function_exists( 'wc_get_logger' )` check (§2): the theme stays active when WooCommerce is deactivated.

### 8.2 Severity Levels

PSR-3 compatible. From most to least severe:

1. `emergency` — system is unusable
2. `alert` — immediate action required
3. `critical` — critical condition
4. `error` — runtime error
5. `warning` — exceptional but recoverable
6. `notice` — normal but significant event
7. `info` — informational
8. `debug` — debug-level message

### 8.3 Best Practices

- **Prefer typed wrappers** (`->info()`, `->error()`, `->warning()`, …) over the generic `->log( $level, … )`. Typed wrappers are easier to grep and harder to typo.
- **Messages are non-translatable English sentences.** Logs are diagnostic artifacts read by developers and support engineers — they are not user-facing strings. Skip `__()` / `_e()` here.
- **One line per message.** Structured payloads (objects, arrays, IDs) go in `$context`, not inside the message string.
- **Use `wc_doing_it_wrong()` — not the logger — for developer-facing API misuse.** Misuse warnings should surface during development; the logger is for runtime events.

## 9. Core Functions and Namespaces

### 9.1 Retrieval Functions

| Function | Returns |
|---|---|
| `wc_get_product( int\|WP_Post $product )` | `WC_Product` instance (or `null` / `false`) |
| `wc_get_order( int\|WP_Post $order )` | `WC_Order` or `WC_Order_Refund` instance (or `false`) |
| `wc_get_template( string $template_name, array $args = [] )` | Includes a WooCommerce template part, passing `$args` as extracted variables |

### 9.2 Core Interfaces (for plugin / extension work)

- `WC_Logger_Interface`
- `WC_Log_Handler_Interface`
- `WC_Product_Data_Store_Interface`
- `WC_Order_Data_Store_Interface`
- `WC_Customer_Data_Store_Interface`
- `WC_Order_Item_Data_Store_Interface`

### 9.3 Namespace Map

| Namespace | Purpose |
|---|---|
| `Automattic\WooCommerce` | Modern core (the canonical entry point) |
| `Automattic\WooCommerce\Internal\Admin\Logging` | Logger internals — read-only reference |
| `Automattic\WooCommerce\Internal\DataStores` | Namespaced data stores (e.g. `Orders\OrdersTableDataStore`) |
| `Automattic\WooCommerce\Blocks` | Block registration, Cart/Checkout integration |
| `Automattic\WooCommerce\StoreApi` | Store API routes and schemas used by the Cart/Checkout blocks |
| `Automattic\WooCommerce\RestApi` | REST API package bootstrap — lives in `includes/rest-api/`, not `src/` |

Every namespace above except `Automattic\WooCommerce\RestApi` maps 1:1 to a filesystem path inside `wp-content/plugins/woocommerce/src/`. Legacy `WC_*` classes are **not** namespaced: they are global classes under `wp-content/plugins/woocommerce/includes/` (e.g. the main `WooCommerce` class returned by `WC()`, `WC_Abstract_Order` in `includes/abstracts/`, the `WC_*_Data_Store_CPT` classes in `includes/data-stores/`). When a function or class is unclear, grep those trees rather than guessing — the source is the canonical reference.

## 10. Common WooCommerce Pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Cart/Checkout layout broken at certain widths | Used `@media` instead of `@container`, or nested a second `container-type` between the block root and the target | Write `@container` queries against the container WooCommerce already declares on the block root (`container-type: inline-size`); add no other container in between |
| Product element block renders empty (no error) | No product context: no product can be resolved from the block's `postId` context (see §2 Ancestor gate) | Nest it in `woocommerce/single-product`, `woocommerce/product-template`, or `core/post-template` per the ancestor column (price, rating, sale-badge, stock and summary may also sit directly in the Single Product template) |
| Theme styles break on WooCommerce update | Selector depended on internal block DOM structure (Internal Privacy gate) | Re-implement via `theme.json`, a block style variation, or the documented class prefixes in §5.1 |
| `is_shop()` returns false in `functions.php` | Called at global scope, before `posts_selection` | Move logic into a callback hooked to `wp` (or any later action) |
| Cart filter throws for administrators / logs a console error | Filter callback returned a different type than it received, or a string missing a required placeholder (§6.2) | Read the error message; return the same type and keep the placeholders |
| `cartItemScreenReaderPrice` filter rejected | Returned string lacks `<quantity/>`, `<productName/>` or `<price/>` | Include all three placeholders in the returned string |
| Legacy class `.price` styled in theme conflicts with editor chrome | Unprefixed class collides with editor + third-party plugins | Rename to `.wc-block-components-product-price__value` (or use a block style variation) |
| Template override is ignored | Filename mismatch with the WooCommerce default | The file in `templates/` must match the WooCommerce template name exactly (e.g. `single-product.html`, not `single_product.html`) |
| Fatal "Call to undefined function `wc_get_logger()`" | Called before WooCommerce has loaded (once loaded it always returns a logger) | Hook setup into `woocommerce_loaded` or later (e.g. `init`) |

## 11. When the Reference Falls Short

This file covers the stable, documented WooCommerce surface as of the project's vetted notes. If the user's question touches a feature that is not in the registries above, do not guess block names, attribute names, or filter names — grep the WooCommerce plugin source directly:

- Block definitions: `wp-content/plugins/woocommerce/src/Blocks/BlockTypes/` (PHP) and the `block.json` files under `wp-content/plugins/woocommerce/assets/client/blocks/` (metadata). The installed plugin ships only compiled JS; the readable JS/TS source is in the WooCommerce GitHub repository under `plugins/woocommerce/client/blocks/assets/js/blocks/`.
- Cart/Checkout filters: `plugins/woocommerce/client/blocks/packages/public-api/blocks-checkout/filter-registry/` in the WooCommerce GitHub repository (not shipped in the installed plugin).
- REST schemas: `wp-content/plugins/woocommerce/src/StoreApi/Schemas/`.

Report what you found from source rather than inventing API shapes — the Internal Privacy and Block Name gates apply just as strictly to "things I'm pretty sure exist".
