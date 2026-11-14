# WooCommerce Additional Checkout Fields API

This reference covers `woocommerce_register_additional_checkout_field` — the supported way
to add custom data collection to the **block-based** Cart/Checkout without `unset()` hacks,
fragile array filtering, or template overrides. It is the modern replacement for the legacy
`woocommerce_checkout_fields` filter and is the only path that keeps custom data a
first-class citizen in the Store API and React frontend.

For the runtime status/observer model that *fires while these fields are validated and
submitted* (`onCheckoutValidation`, `onPaymentSetup`, statuses), see
`references/woocommerce-checkout-lifecycle.md`. For coding-standard rules referenced here
(prefixing, `function_exists`, textdomain), see `references/woocommerce-standards.md`.

## 1. When to Load This File

Load this file **before writing any code** when the user asks to:

- Add, modify, or remove a field in the block Cart/Checkout (contact / address / order area).
- Register a custom field that must persist to customer or order meta.
- Sanitize or validate a checkout field server-side.
- Show/hide or require a checkout field conditionally based on cart state (JSON Schema).
- Read a custom checkout value back out of an order or customer object.
- Keep a legacy `woocommerce_checkout_fields` integration working alongside the block checkout.

If the task is only *styling* Cart/Checkout or composing `woocommerce/*` blocks, stay in
`references/woocommerce.md`. If it is about the *submission lifecycle* (payment handshake,
redirects), use `references/woocommerce-checkout-lifecycle.md`.

## 2. Pre-Write Gates (Checkout Fields)

All must pass before writing code. These are **in addition to** the gates in `SKILL.md` and
`references/woocommerce.md → §2`.

- **Timing gate** — registration must run **on or after `woocommerce_init`**. Registering
  too early triggers a `_doing_it_wrong()` notice and can load translations before they are
  ready. Always wrap the call in `add_action( 'woocommerce_init', … )`.
- **ID-syntax gate** — every `id` must be `namespace/field-name`. The `/` is a mandatory
  separator; the namespace must be your unique prefix. An `id` without a `/`, or one that is
  already registered, is rejected and the field is not registered — so this function cannot
  re-register or override a default (core) field (§7).
- **Location gate** — `location` must be exactly one of `contact`, `address`, or `order`.
  No other values exist. The location decides persistence and UI placement (§3) — choosing
  wrong causes data loss or a fragmented customer profile, not an error.
- **Type gate** — `type` must be exactly `text`, `select`, `checkbox`, or `date` (default
  `text`). These are the only supported field types. `select` additionally requires an
  `options` array.
- **Pointer-escaping gate** — in any JSON Schema rule (§6), a field ID's `/` must be escaped
  as `~1`. `my-plugin/field` is referenced as `my-plugin~1field`. An unescaped slash is read
  as a pointer path segment and silently never matches.

## 3. Field Locations — Placement & Persistence

`location` is the single most important architectural decision: it dictates UI placement,
where the data is stored, and how long it lives.

| Location | Identifier | UI Placement | Persistence | Lifecycle |
|---|---|---|---|---|
| Contact information | `contact` | Form header, next to the email field | **Customer Meta + Order Meta** — shows in "Account Details" on My Account | Long-lived, tied to shopper identity (e.g. Loyalty ID) |
| Addresses | `address` | **Both** Shipping and Billing forms | **Customer Meta + Order Meta** (dual-saved) | Reused across orders, pre-fills for returning customers |
| Order information | `order` | "Order Information" inner block (defaults to form footer; repositionable via Gutenberg block controls) | **Order Meta only** | Transient — new orders start empty (e.g. Gift Message) |

### 3.1 Address-Field Bifurcation (critical)

Registering **one** field in the `address` location renders it in **both** the shipping and
billing forms. If the shopper unchecks "Same as billing," WooCommerce collects and stores
**two independent values**:

- `_wc_shipping/my-plugin/field`
- `_wc_billing/my-plugin/field`

Always design address-location logic to handle two values, even when the UI looks unified.
The `$group` parameter in location validation (§5.2) tells you which one you are processing.

## 4. Field Types & Options

Registration happens inside the `woocommerce_init` action:

```php
/**
 * Registers additional checkout fields.
 */
function {{THEME_PREFIX}}_register_checkout_fields(): void {
	woocommerce_register_additional_checkout_field(
		array(
			'id'            => '{{TEXT_DOMAIN}}/reference-code',
			'label'         => __( 'Reference Code', '{{TEXT_DOMAIN}}' ),
			'optionalLabel' => __( 'Reference Code (if available)', '{{TEXT_DOMAIN}}' ),
			'location'      => 'address',
			'type'          => 'text',
			'required'      => false,
		)
	);
}
add_action( 'woocommerce_init', '{{THEME_PREFIX}}_register_checkout_fields' );
```

### 4.1 Type Reference

| Type | Required options | Type-specific options |
|---|---|---|
| `text` | `id`, `label`, `location` | — |
| `select` | `id`, `label`, `location`, `options` | `placeholder` |
| `checkbox` | `id`, `label`, `location` | `error_message` (only used when the checkbox is required) |
| `date` | `id`, `label`, `location` | `min`, `max` |

The meta-key prefix is decided by the field's `location`, not its type (§8.3).

- **`optionalLabel`** (all types) — if omitted, WooCommerce appends `(optional)` to the label
  automatically. Define it explicitly for a clean, custom UI.
- **`options`** (select only) — array of `{ value, label }` pairs.
- **`required`** — boolean, or a JSON Schema rule array for conditional requirement (§6).
- **`attributes`** — array of extra HTML attributes for the input. Only `maxLength`,
  `readOnly`, `pattern`, `autocomplete`, `autocapitalize`, `title`, `aria-*` and `data-*`
  are accepted; anything else is dropped with a `_doing_it_wrong()` notice.

## 5. Sanitization & Validation

Server-side enforcement is the final defense regardless of client state. **Sanitization
always runs before validation** — sanitize formats the data, validation judges it.

### 5.1 Single-Field Sanitize then Validate

Two registration styles exist; both appear in the source docs. They reach the same result
but their callback contracts differ.

**Inline callbacks on the registration** (each is called with `( $value, $field )`; the
validate callback **returns** a `WP_Error` to fail):

```php
woocommerce_register_additional_checkout_field( array(
	'id'                => '{{TEXT_DOMAIN}}/government-id',
	'label'             => __( 'Government ID', '{{TEXT_DOMAIN}}' ),
	'location'          => 'address',
	'type'              => 'text',
	'sanitize_callback' => static fn( string $value ): string =>
		strtoupper( str_replace( ' ', '', $value ) ), // "abc 123" -> "ABC123"
	'validate_callback' => static function ( $value, array $field ): ?WP_Error {
		if ( ! preg_match( '/^[A-Z0-9]{5}$/', (string) $value ) ) {
			return new WP_Error( '{{TEXT_DOMAIN}}-invalid-id', __( 'The ID must be 5 alphanumeric characters.', '{{TEXT_DOMAIN}}' ) );
		}
		return null;
	},
) );
```

**Or via the global filter/action hooks** (useful when the field is registered elsewhere; here the error object is passed in):

- `woocommerce_sanitize_additional_field` (filter) — formatting stage.
- `woocommerce_validate_additional_field` (action) — rule-enforcement stage.

```php
/**
 * Validates the government ID field.
 */
function {{THEME_PREFIX}}_validate_government_id( WP_Error $errors, string $field_key, mixed $field_value ): void {
	if ( '{{TEXT_DOMAIN}}/government-id' === $field_key && ! preg_match( '/^[A-Z0-9]{5}$/', (string) $field_value ) ) {
		$errors->add( '{{TEXT_DOMAIN}}-invalid-id', __( 'The ID must be 5 alphanumeric characters.', '{{TEXT_DOMAIN}}' ) );
	}
}
add_action( 'woocommerce_validate_additional_field', '{{THEME_PREFIX}}_validate_government_id', 10, 3 );
```

**Validation rules (non-negotiable):**

- **Match the contract to the style** — an inline `validate_callback` fails by *returning* a
  `WP_Error` (returning `false` produces a generic "Please provide a valid …" error) and
  passes by returning nothing. The `woocommerce_validate_additional_field` action and the
  location actions (§5.2) fail by calling `$errors->add()` on the `WP_Error` they receive;
  their return value is ignored.
- **Never give `validate_callback` the action's three-parameter signature** — it is called
  with two arguments, so the call throws, WooCommerce catches it with a warning, and your
  check never runs.
- **Namespace every error code** — prefix with your plugin namespace so codes from core or
  other extensions don't collide and overwrite your message.

### 5.2 Multi-Field / Cross-Field Validation

When a field's validity depends on a sibling (e.g. "Confirm ID", or a tax ID that depends on
the selected country), use the location-scoped action. It receives **all** fields in the group.

| Location | Action hook |
|---|---|
| `contact` | `woocommerce_blocks_validate_location_contact_fields` |
| `address` | `woocommerce_blocks_validate_location_address_fields` |
| `order` | `woocommerce_blocks_validate_location_order_fields` |

The hook name is built from the **location** (`address`, `contact`, `order`). The third
parameter `$group` is `'billing'` or `'shipping'` for the **address** location — essential
because of address-field bifurcation (§3.1) — and `'other'` for `contact` and `order`.

```php
/**
 * Checks that the two ID fields of an address match. $group is 'billing' or 'shipping'.
 */
function {{THEME_PREFIX}}_validate_matching_ids( WP_Error $errors, array $fields, string $group ): void {
	if ( ( $fields['{{TEXT_DOMAIN}}/id'] ?? '' ) !== ( $fields['{{TEXT_DOMAIN}}/confirm-id'] ?? '' ) ) {
		$errors->add( '{{TEXT_DOMAIN}}-mismatch', __( 'The ID values do not match.', '{{TEXT_DOMAIN}}' ) );
	}
}
add_action( 'woocommerce_blocks_validate_location_address_fields', '{{THEME_PREFIX}}_validate_matching_ids', 10, 3 );
```

## 6. Conditional Logic via JSON Schema

WooCommerce Blocks uses **JSON Schema Draft-07** for the `required`, `hidden`, and
`validation` properties. Rules are evaluated in real time on the client **and** enforced on
the server — one definition, two enforcement points ("single source of truth").

### 6.1 The Document Object & Pointers

Rules evaluate against the **Document Object**, which describes current checkout state. Its
top-level keys are `cart`, `checkout` and `customer`, and all of its properties are
**snake_case** (`cart.totals.total_price`, `cart.shipping_rates`, `cart.prefers_collection`).
Navigation uses JSON pointers:

- **Slash escaping** — a field ID containing `/` must use `~1`. `my-plugin/field` →
  `my-plugin~1field`. (See the Pointer-escaping gate in §2.)
- **`0/` prefix** — root-level navigation, e.g. `0/customer/address/phone`.
- **`1/` prefix** — relative navigation, steps back one level to a sibling within the same
  address group, e.g. `1/phone`.

### 6.2 Required / Hidden / Validation Example

This field is hidden when Local Pickup is selected, required when it is not, and carries a
real-time validation message via the **non-standard `errorMessage` keyword** (a WooCommerce
extension to Draft-07). Each of the three properties takes **one schema** (an associative
array). `required` and `hidden` describe the whole Document Object, so start from `cart`,
`checkout` or `customer`; `validation` describes only this field's own value. **Never** wrap
a rule in a list of schemas — the registration is rejected and the field is not registered.

```php
woocommerce_register_additional_checkout_field( array(
	'id'       => '{{TEXT_DOMAIN}}/delivery-instructions',
	'label'    => __( 'Delivery Instructions', '{{TEXT_DOMAIN}}' ),
	'location' => 'order',
	'type'     => 'text',
	'required' => array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'prefers_collection' => array( 'const' => false ),
				),
			),
		),
	),
	'hidden'   => array(
		'type'       => 'object',
		'properties' => array(
			'cart' => array(
				'properties' => array(
					'prefers_collection' => array( 'const' => true ),
				),
			),
		),
	),
	'validation' => array(
		'type'         => 'string',
		'minLength'    => 5,
		'errorMessage' => __( 'Please provide a valid instruction.', '{{TEXT_DOMAIN}}' ),
	),
) );
```

### 6.3 Performance Caveat

Schemas are re-evaluated on **every** frontend state change. Keep them shallow — avoid deep
nesting and minimize the number of conditions per registration, or the checkout UI lags.

## 7. Modifying Default (Core) Fields

`woocommerce_register_additional_checkout_field` **cannot** change a built-in field: an `id`
without a `/` is rejected, and so is an `id` that is already registered. **Never** try to
re-register a core field. The block checkout's core field keys are:

```
email       country     first_name  last_name
company     address_1   address_2   city
state       postcode    phone
```

Core address fields follow the country locale, so adjust them per country with the
`woocommerce_get_country_locale` filter. A change applies to both the shipping and the
billing form:

```php
/**
 * Hides the postcode field for addresses in Algeria.
 */
function {{THEME_PREFIX}}_hide_dz_postcode( array $locale ): array {
	$locale['DZ']['postcode']['required'] = false;
	$locale['DZ']['postcode']['hidden']   = true;

	return $locale;
}
add_filter( 'woocommerce_get_country_locale', '{{THEME_PREFIX}}_hide_dz_postcode' );
```

Company, Phone and Address line 2 are different: their default state is stored in the
options `woocommerce_checkout_company_field`, `woocommerce_checkout_phone_field` and
`woocommerce_checkout_address_2_field`, each `optional`, `required` or `hidden`.

### 7.1 Editing States — the `XX` Placeholder

States are added or changed with the `woocommerce_states` filter, not with the checkout
fields API. In WooCommerce's reference snippet `XX` is a placeholder: replace it with the
two-letter country code both as the array key and as the prefix of each state ID
(`$states['XX'] = array( 'XX1' => …, 'XX2' => … )`).

## 8. Reading Field Values Back

Always prefer the **helper methods** over direct meta-key access — they survive future schema
migrations. They are methods of the `CheckoutFields` service, not global functions, and both
take a `$group`: `'billing'` or `'shipping'` for address fields, `'other'` (the default) for
contact and order fields.

| Helper | Returns |
|---|---|
| `get_field_from_object( string $key, WC_Data $wc_object, string $group = 'other', bool $apply_defaults = true )` | One field's value from a customer/order object |
| `get_all_fields_from_object( WC_Data $wc_object, string $group = 'other', bool $all = false )` | Array of all registered additional fields in that group on the object |

```php
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;

$checkout_fields = Package::container()->get( CheckoutFields::class );
$billing_code    = $checkout_fields->get_field_from_object( '{{TEXT_DOMAIN}}/reference-code', $order, 'billing' );
$order_fields    = $checkout_fields->get_all_fields_from_object( $order, 'other' );
```

### 8.1 The Stale-Data Trap (most common bug)

During the `place_order` lifecycle, **never** read a field from a freshly loaded customer
(`new WC_Customer( $order->get_customer_id() )`) — the persistent customer record is only
updated *after* the order is processed, so you get `null` or stale data. Always read from the
**object being processed** (the order) using `get_all_fields_from_object( $order, $group )`,
from the session customer `wc()->customer`, or from the Store API inputs.

### 8.2 Guest vs Logged-In Persistence

| Customer state | Mechanism | Behavior |
|---|---|---|
| Guest | Session-based storage | Available while the session is valid |
| Logged-in | Database persistence | Only persisted **after** the order is placed |

### 8.3 Direct Meta-Key Access (external engines only)

For email builders or external systems that cannot call PHP helpers, data is stored under:

- **Address:** `_wc_billing/[namespace]/[field]` and `_wc_shipping/[namespace]/[field]`
- **Contact / Order:** `_wc_other/[namespace]/[field]`

Prefer the constants on the `CheckoutFields` class over hardcoding:

- `CheckoutFields::BILLING_FIELDS_PREFIX` → `_wc_billing/`
- `CheckoutFields::SHIPPING_FIELDS_PREFIX` → `_wc_shipping/`
- `CheckoutFields::OTHER_FIELDS_PREFIX` → `_wc_other/`

**Checkbox caveat:** direct DB reads return the strings `"1"`, `"0"`, or `""`.
`get_field_from_object()` casts these to real booleans (`true` / `false`);
`get_all_fields_from_object()` returns the stored strings unchanged. Read checkboxes with
`get_field_from_object()` to keep your business logic type-safe.

## 9. Backward Compatibility with Legacy Meta

During migration from classic to block checkout, keep legacy meta-keys in sync with two hooks.
The `{$key}` in the read filter is the **full field ID including the namespace**.

- **Saving** — `woocommerce_set_additional_field_value`: mirror block-based data back into
  legacy meta when an order saves.
- **Reading** — `woocommerce_get_default_value_for_{$key}`: pull a value from legacy meta to
  pre-fill a block field.

## 10. Common Pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| `_doing_it_wrong()` notice: "should be registered on the woocommerce_init action or later" | Registered too early | Wrap registration in `add_action( 'woocommerce_init', … )` |
| Field never appears; notice says "A checkout field id must consist of namespace/name" or "The field is already registered" | `id` has no `/` (this includes every core field key), or reuses an already registered ID | Use a unique `namespace/field-name` for every field |
| Address field saves only one value | Forgot bifurcation — read only billing OR shipping | Handle both `_wc_billing/…` and `_wc_shipping/…`; use `$group` in validation |
| Inline `validate_callback` never fails | Gave it the action's `( $errors, $key, $value )` signature, or called `add()` on a local error object without returning it | `validate_callback` receives `( $value, $field )` and must **return** the `WP_Error`; `$errors->add()` is for the action hooks |
| Error message overwritten by another plugin | Un-namespaced error code | Prefix every error code with your namespace |
| Conditional rule silently never matches | `/` in field ID not escaped in the JSON pointer | Escape as `~1` (`my-plugin~1field`) |
| Checkbox logic treats `"0"` as truthy | Read meta directly instead of via helper | Use `get_field_from_object()`; it returns real booleans |
| Custom value is `null` during order processing | Read a freshly loaded `WC_Customer` mid-`place_order` (stale-data trap) | Read from the order object via `get_all_fields_from_object( $order, $group )` |
| Address field value comes back empty from a helper | Omitted `$group`, which defaults to `'other'` | Pass `'billing'` or `'shipping'` for `address`-location fields |

## 11. Architect's Checklist

Before shipping a checkout-fields integration, verify:

1. **Timing** — registration runs on/after `woocommerce_init`.
2. **Mapping** — the `location` matches the required persistence (Customer Meta vs Order Meta).
3. **Sanitization** — formatting runs *before* validation.
4. **Integrity** — `WP_Error` codes are namespaced; an inline `validate_callback` returns its `WP_Error`, the action hooks call `$errors->add()`.
5. **Schema syntax** — every field-ID `/` is escaped as `~1` in JSON pointers; schemas are shallow.
6. **Compatibility** — legacy filters use the full `namespace/field` ID for data continuity.

## 12. When the Reference Falls Short

This file covers the documented Additional Checkout Fields surface from the project's vetted
notes. If the user needs an undocumented field type, a Store API schema detail, or an
attribute not listed above, **do not invent it** — grep the WooCommerce source:

- Field registration & `CheckoutFields`: `wp-content/plugins/woocommerce/src/Blocks/Domain/Services/CheckoutFields.php`
- Store API schemas: `wp-content/plugins/woocommerce/src/StoreApi/Schemas/`

Report what you find from source rather than guessing API shapes.
