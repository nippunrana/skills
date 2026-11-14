# WooCommerce Cart & Checkout JS Lifecycle, Observers & SlotFill

This reference covers the **client-side** extensibility model of the block Cart/Checkout: the
data stores, the status state machine, the event-emitter / observer pattern that drives the
"Place Order" handshake, the SlotFill injection points, and the native DOM events that
replaced jQuery. It is the JS counterpart to `references/woocommerce-checkout-fields.md`
(which covers the PHP field-registration side).

For the static value-transform filters (`cartItemPrice`, `totalLabel`, etc. via
`@woocommerce/blocks-checkout`) see `references/woocommerce.md → §6`. This file adds the
**event** layer; `registerCheckoutFilters()` remains the only filter-registration path (§7).

## 1. When to Load This File

Load **before writing any JS** when the user asks to:

- Run custom logic when checkout validates, when payment is set up, or after the order
  succeeds/fails (`onCheckoutValidation`, `onPaymentSetup`, `onCheckoutSuccess`/`Fail`).
- Block or delay order placement until async work (third-party tax, fraud check) completes.
- Read checkout/cart state in React (`isCalculating`, totals, addresses) via the data stores.
- Inject a React component into the Cart/Checkout (SlotFill: order meta, shipping packages,
  local pickup, discounts).
- React to add-to-cart / remove-from-cart events, or bridge legacy jQuery cart events.

If the task is server-side field registration/validation, use
`references/woocommerce-checkout-fields.md`. If it is CSS/markup, use `references/woocommerce.md`.

## 2. Pre-Write Gates (Checkout Lifecycle)

- **Single-source-of-truth gate** — the Checkout block owns *all* server communication.
  Extensions must **never** POST to the server directly; they pass data into the block's
  request via observer return values (`meta.paymentMethodData`, `meta.billingAddress`,
  `meta.shippingAddress`). Bypassing the block desynchronizes state and corrupts the order.
- **No-mutation gate** — observers run in **strict sequential order**. An observer must
  **never** mutate global or context state directly: later observers won't see the change.
  Always communicate results back through the **returned response object** (Success/Fail/Error).
- **Structured-return gate** — an observer with nothing to report returns `true` (or
  nothing) and the flow continues. To block the flow or pass data, return an object with a
  `type` of `success`, `failure` or `error`. Only objects that carry a `type` are collected
  as responses: a bare `false`, a string, or an object without `type` is ignored, so
  **never** rely on one to stop the checkout.
- **Gate-condition awareness** — `onPaymentSetup` only fires when all three gates are open
  (§5). If your payment logic "never runs," check `isProcessing` / `hasError` / `isCalculating`
  before assuming a wiring bug.

## 3. Data Stores

State lives in `wp.data` stores — the "single source of truth" that keeps the React UI
and the underlying data in real-time sync. The two this file relies on:

- `wc/store/cart` — cart contents, totals, coupons, shipping rates.
- `wc/store/checkout` — checkout status, flags, and order data.

Payment status and inline field errors have their own stores, `wc/store/payment` and
`wc/store/validation`.

`useSelect` is a React hook, so it works only inside a component — for example one rendered
by a fill (§6.1). Script dependencies: `wc-blocks-data-store` (it registers the store),
`wp-data`, `wp-element`; wrap and load the file as
described in `references/woocommerce.md → §6.4`.

```js
const { useSelect } = window.wp.data;
const { createElement } = window.wp.element;

const BusyIndicator = () => {
	const isCalculating = useSelect(
		( select ) => select( 'wc/store/checkout' ).isCalculating(),
		[]
	);

	return createElement( 'div', { className: isCalculating ? 'is-loading' : '' } );
};
```

Outside React, read the store once with `window.wp.data.select()`, as §5.6 does.

To programmatically **halt** checkout until custom async logic finishes (e.g. while a
third-party tax service responds), dispatch the `disableCheckoutFor` thunk with an async
function: `window.wp.data.dispatch( 'wc/store/checkout' ).disableCheckoutFor( async () => { … } )`.
The checkout stays in its calculating state until that function settles. This is the
supported way to keep "Place Order" disabled without racing the status machine.

## 4. The Status State Machine

The checkout moves through a fixed sequence of statuses. Knowing the current chapter tells you
which events fire and which buttons are live.

| Status | Meaning | What happens |
|---|---|---|
| `IDLE` | Baseline | Set after load and after every state change; ready for input |
| `BEFORE_PROCESSING` | The inspection | Fires `onCheckoutValidation`; observers vet the data |
| `PROCESSING` | The handshake | Fires `onPaymentSetup`; payment method confirms data |
| `AFTER_PROCESSING` | Server replied | Branches to `onCheckoutSuccess` or `onCheckoutFail` |
| `COMPLETE` | Done | Shopper redirected to the "Order Received" page |

### 4.1 `isCalculating` — UI Safety Behaviors

Recalculating totals is **not** one of the statuses above. It is tracked by a separate
boolean, `isCalculating`, which is independent of the status and is `true` while totals are
being recalculated (a coupon is applied, a shipping rate is selected) or while a
`disableCheckoutFor` call is running.

While `isCalculating` is `true`, the block performs three protections so a shopper can't
submit against an outdated total:

1. Disables the **Place Order** button (Checkout block).
2. Disables the **Proceed to Checkout** button (Cart block).
3. Shows a **loading state for Express Payment** methods (Apple Pay / Google Pay) until totals settle.

## 5. Event Emitters & Observers

The block **emits** events; extensions register **observers** that react. Four core events:

### 5.1 `onCheckoutValidation` (during `BEFORE_PROCESSING`)

The "great inspection." Each observer can inspect the data and block submission. To pass,
return `true`. To fail, return an object with `type: 'error'` plus one or both of:

- `errorMessage` — a string, rendered as a general notice.
- `validationErrors` — an object whose **keys must match the field's validation error ID**
  (e.g. `billing_email`, `shipping_country`, `coupon`) so the message appears next to the
  right field. Each value is `{ message: string, hidden: boolean }`.

### 5.2 `onPaymentSetup` (during `PROCESSING`)

The payment "handshake." It is a **blocking observer chain** — it stops at the first observer
that returns an `error` or `failure` response (or throws). It fires **only when all three
gates are open**:

1. `isProcessing` is `true`,
2. `hasError` is `false`,
3. `isCalculating` is `false`.

Responses:

- **Success** — return `{ type: 'success' }`, optionally with `meta: { paymentMethodData }`.
  That data is bundled into the server request that finalizes the order.
- **Failure** — return `{ type: 'failure' }` or `{ type: 'error' }`, optionally with a
  `message`. This sets `hasError = true` and halts the journey so the shopper can fix the
  problem.

### 5.3 `onCheckoutSuccess` / `onCheckoutFail` (during `AFTER_PROCESSING`)

After the server replies, the flow branches. On success, observers receive five fields:

1. **Redirect URL** — usually the "Order Received" page.
2. **Order ID**.
3. **Customer ID**.
4. **Order Notes** — any note the customer left.
5. **Payment Result** — `{ paymentStatus, paymentDetails }` (an arbitrary server-side object).

### 5.4 Retry Logic

`AFTER_PROCESSING` doesn't always end in `COMPLETE`. When checkout ends with an error (e.g. a
declined card), the status returns to `IDLE` so the shopper can try again — unless an
observer's `error`/`failure` response sets `retry: false`, which sends it to `COMPLETE`
instead (in `onCheckoutFail` the response must also carry a `message` string to be honored).
An unset `retry` counts as `true`. Only when the status reaches `COMPLETE` is the shopper
redirected.

### 5.5 The Cardinal Rule

> Observers must **never** update global or context state directly. Because they run
> sequentially, later observers won't see the change. Communicate everything through the
> returned response object (Success / Fail / Error).

### 5.6 Registering an Observer

`onCheckoutValidation`, `onCheckoutSuccess` and `onCheckoutFail` are subscribe functions on
the `checkoutEvents` object of the `wc.blocksCheckoutEvents` global, provided by
WooCommerce's `wc-blocks-checkout-events` script. Each takes `( callback, priority = 10 )` —
lower priorities run first — and returns an **unsubscribe** function.

**Never** destructure them straight from the global, as WooCommerce's own docs do. In
WooCommerce 11.3.0 the global holds `checkoutEvents`, `checkoutEventsEmitter` and
`CHECKOUT_EVENTS`, so that form yields `undefined` and the script stops with a `TypeError`.

Script dependencies: `wc-blocks-checkout-events`, plus `wc-blocks-data-store` and `wp-data`
when the callback reads a store, and `wp-i18n` when it translates. Load the file as described
in `references/woocommerce.md → §6.4`.

```js
const { checkoutEvents } = window.wc.blocksCheckoutEvents;
const { select } = window.wp.data;
const { __ } = window.wp.i18n;

const unsubscribe = checkoutEvents.onCheckoutValidation( () => {
	const orderNotes = select( 'wc/store/checkout' ).getOrderNotes();

	if ( orderNotes.length <= 500 ) {
		return true;
	}

	return {
		type: 'error',
		errorMessage: __( 'Please shorten the order note to 500 characters.', '{{TEXT_DOMAIN}}' ),
	};
} );
```

Call `unsubscribe()` when the observer is no longer needed; inside a React component, return
it from the `useEffect` that subscribed.

`onPaymentSetup` is **not** on that global. WooCommerce passes it (together with the three
above) to a registered payment method's component in the `eventRegistration` prop, so
subscribe to it from payment-method code only.

## 6. SlotFill Injection Points

Slots let you render React components into protected areas of Cart/Checkout **without**
template overrides or touching private internal DOM. Each fill receives `cart` (camelCase
Store API data), `extensions` (third-party data bag), and `context`
(`woocommerce/cart` or `woocommerce/checkout`).

| Slot | Renders |
|---|---|
| `ExperimentalOrderMeta` | Above the primary action buttons |
| `ExperimentalOrderShippingPackages` | Inside the shipping step / options |
| `ExperimentalOrderLocalPickupPackages` | Inside the Local Pickup block |
| `ExperimentalDiscountsMeta` | Directly below the coupon input |

These are **Experimental** APIs (note the prefix) — they may change between releases; isolate
their use and don't build deep dependencies on their internal markup.

### 6.1 Registering a Fill

A fill is rendered by a plugin registered with `registerPlugin()` from `wp.plugins`, and the
`scope` must always be `woocommerce-checkout` (the Cart block uses that scope too). The fill
components are on the `wc.blocksCheckout` global. `ExperimentalOrderMeta` hands `cart`,
`extensions` and `context` to each of its top-level children as props, so make the child a
component, not a bare element. Script dependencies: `wc-blocks-checkout`, `wp-plugins`, `wp-element`,
`wp-i18n`; load the file as described in `references/woocommerce.md → §6.4`.

```js
const { registerPlugin } = window.wp.plugins;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { ExperimentalOrderMeta } = window.wc.blocksCheckout;

const GiftNote = ( { context } ) => {
	if ( context !== 'woocommerce/checkout' ) {
		return null;
	}

	return createElement(
		'div',
		{ className: 'wc-block-components-totals-wrapper' },
		__( 'Gift wrapping is free on every order.', '{{TEXT_DOMAIN}}' )
	);
};

const render = () => createElement( ExperimentalOrderMeta, null, createElement( GiftNote ) );

registerPlugin( '{{THEME_SLUG}}-order-meta', {
	render,
	scope: 'woocommerce-checkout',
} );
```

WooCommerce's own docs write the same registration in JSX, which needs a build step; the
`createElement` form above does not.

## 7. Line-Item Filters — One Registry, Required Placeholders

Line-item values (`itemName`, `cartItemPrice`, `cartItemClass`, …) have a single registration
path: `registerCheckoutFilters()` (`references/woocommerce.md → §6`). The Cart and Checkout
components read them with `applyCheckoutFilter`, which only runs callbacks registered that
way — **never** register a line-item filter through any other API; it will not be called.
Callbacks receive `( value, extensions, args )`, where `args.context` is `cart` for Cart line
items and `summary` for the Checkout order summary.

```js
const { registerCheckoutFilters } = window.wc.blocksCheckout;
const { __, sprintf } = window.wp.i18n;

registerCheckoutFilters( '{{THEME_SLUG}}', {
	itemName: ( name, extensions, args ) => {
		if ( args?.context !== 'summary' ) {
			return name;
		}

		/* translators: %s: product name. */
		return sprintf( __( '%s (in your order)', '{{TEXT_DOMAIN}}' ), name );
	},
} );
```

When the file already calls `registerCheckoutFilters()` for this namespace, add `itemName`
to that call: a second call with the same namespace replaces the first
(`references/woocommerce.md → §6.2`).

**Price-filter constraint (same as `references/woocommerce.md → §6.2`):** any filter that
returns a price format string (`cartItemPrice`, `saleBadgePriceFormat`,
`subtotalPriceFormat`, `totalValue`) **must** include the `<price/>` substring, and
`cartItemScreenReaderPrice` must include `<quantity/>`, `<productName/>` and `<price/>`.
Otherwise validation fails and the returned value is not applied.

## 8. Native DOM Events (jQuery Replacement)

WooCommerce Blocks moved off jQuery to native DOM events. Legacy jQuery cart events are
bridged by WooCommerce's internal `translateJQueryEventToNative()` helper (not a public
API), which re-emits them as native events named with the `wc-blocks_` prefix so modern code
can listen without jQuery.

| Native event | Fires when | Notable payload |
|---|---|---|
| `wc-blocks_adding_to_cart` | Add-to-cart request initiated | — |
| `wc-blocks_added_to_cart` | Add-to-cart succeeded | `preserveCartData` parameter |
| `wc-blocks_removed_from_cart` | Item removal completed | — |

```js
document.body.addEventListener( 'wc-blocks_added_to_cart', ( event ) => {
	// react to a successful add-to-cart without jQuery
} );
```

## 9. Common Pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Custom payment logic never runs | One of the three `onPaymentSetup` gates is closed | Verify `isProcessing` true, `hasError` false, `isCalculating` false |
| Later observer ignores an earlier observer's change | Mutated global/context state directly (No-mutation gate) | Communicate only via the returned response object |
| Validation error doesn't appear on the field | `validationErrors` key isn't the field's validation error ID, or the value isn't `{ message, hidden }` | Use the exact ID (`billing_email`, `shipping_country`, `coupon`) and value shape |
| Observer's failure result is ignored | Returned `false`, a string, or an object without `type` — only typed objects are collected as responses (Structured-return gate) | Return `{ type: 'error', … }` (or `'failure'`) |
| Declined card doesn't let shopper retry | An observer's error response set `retry: false` | Omit `retry` or set it to `true` so the status returns to `IDLE` |
| Price filter rejected (error thrown for administrators, console error for shoppers) | Return string missing the `<price/>` token | Include `<price/>` in any price-returning filter |
| SlotFill content vanishes after update | Depended on Experimental slot internals | Treat `Experimental*` slots as unstable; isolate usage |
| Extension data never reaches the server | Tried to POST directly instead of via the block | Pass data through observer returns (`meta.paymentMethodData`, address objects) |

## 10. When the Reference Falls Short

This file covers the documented client lifecycle from the project's vetted notes. For an
event, selector, or slot not listed here, **do not guess** — grep the source. The installed
plugin ships only compiled JS (`wp-content/plugins/woocommerce/assets/client/blocks/`), so
read the WooCommerce GitHub repository, where everything below sits under
`plugins/woocommerce/client/blocks/`:

- Checkout data store: `packages/public-api/block-data/checkout/`.
- Checkout events: `packages/public-api/blocks-checkout-events/` and
  `assets/js/base/context/`.
- Cart/Checkout filters: `packages/public-api/blocks-checkout/filter-registry/`.
- SlotFill definitions: `packages/public-api/blocks-checkout/components/`.

Report what you find from source rather than inventing event or slot names.
