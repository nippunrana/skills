# WordPress PHP Coding Standards

**Load this file before writing any PHP** — `functions.php`, `inc/**/*.php`, `render_callback`
closures inside `register_block_type()`, or any PHP pattern file that contains function or
class definitions.

**Scope: new theme/plugin code on PHP 8.2 and later (8.4 by default).** The official WPCS handbook also carries
cautions aimed at WordPress *Core* itself — for example that adding type declarations to existing
Core functions "should be done with extreme care" — that **do not restrict new theme and plugin
code**. This skill requires type declarations on everything it writes. For modern PHP *feature* requirements (typed params, `match`, `??`, etc.) see
`references/architecture.md → PHP Requirements`, and for what changes on a site below PHP 8.4
see `references/php-compatibility.md`. Those answer "which features to use"; this file answers
"how to format WP PHP".

Source: WordPress PHP Coding Standards (official handbook), formatting and naming conventions
subset applicable to new theme/plugin code.

---

## Table of Contents

1. [Array literals — `array()` not `[]`](#1-array-literals--array-not-)
2. [Indentation — real tabs](#2-indentation--real-tabs)
3. [Strings — single quotes by default](#3-strings--single-quotes-by-default)
4. [Spacing — control structures vs. function calls](#4-spacing--control-structures-vs-function-calls)
5. [Yoda conditions and strict comparisons](#5-yoda-conditions-and-strict-comparisons)
6. [Control flow — braces, `elseif`, ternary](#6-control-flow--braces-elseif-ternary)
7. [Casts and operators](#7-casts-and-operators)
8. [Naming conventions](#8-naming-conventions)
9. [File organization](#9-file-organization)
10. [OOP — visibility and modifier order](#10-oop--visibility-and-modifier-order)
11. [Database — `$wpdb->prepare()`](#11-database--wpdb-prepare)
12. [Closing PHP tag](#12-closing-php-tag)
13. [Includes — `require_once` without parens](#13-includes--require_once-without-parens)
14. [Dynamic hooks — interpolation not concatenation](#14-dynamic-hooks--interpolation-not-concatenation)
15. [Prohibited constructs](#15-prohibited-constructs)
16. [Relationship to architecture.md § PHP Requirements](#16-relationship-to-architecturemd--php-requirements)

---

## 1. Array literals — `array()` not `[]`

WordPress mandates the long array syntax. The short syntax is prohibited — it's harder to scan
for developers unfamiliar with PHP, and less accessible for those using screen readers or
reduced-vision tools.

```php
// ✅ correct
$colors = array( 'red', 'green', 'blue' );

register_block_style(
	'core/group',
	array(
		'name'  => 'hero-section',
		'label' => 'Hero Section',
	)
);

// ❌ wrong — short syntax
$colors = [ 'red', 'green', 'blue' ];

register_block_style( 'core/group', [ 'name' => 'hero-section' ] );
```

**Trailing comma.** In a **multi-line** array, add a trailing comma after the last item — cleaner
git diffs when items are added or reordered. A single-line array takes no trailing comma:

```php
// ✅ trailing comma on last item
$args = array(
	'post_type'      => 'page',
	'posts_per_page' => 10, // ← trailing comma
);
```

**Array access and append.** The prohibition is on *array literal syntax* only. Reading with
`$arr['key']` and appending with `$arr[] = $val` are both correct and unchanged.

**Type declarations.** The WPCS handbook discourages the `array` keyword in *type
declarations* (preferring `iterable`). This skill uses `: array` in typed signatures for
clarity on new code, since `iterable` also matches `Traversable` objects and the stricter type
matches developer expectations. This is a deliberate trade-off; either choice is defensible
for new theme/plugin code.

---

## 2. Indentation — real tabs

Use real **tab characters** for logical indentation at the start of each line. Spaces are
allowed *mid-line only* for visual alignment (e.g. lining up `=>` in an associative array).

This matches CSS (`references/coding-standards.md`) and JS (`references/js-coding-standards.md`).
Tabs let each developer set their preferred visual width without touching the file.

```php
// ✅ correct — tab-indented
function my_theme_register_pattern_category(): void {
	register_block_pattern_category(
		'my-theme',
		array( 'label' => __( 'My Theme', 'my-theme' ) )
	);
}

// ❌ wrong — space-indented
function my_theme_register_pattern_category(): void {
    register_block_pattern_category( 'my-theme', array( 'label' => __( 'My Theme', 'my-theme' ) ) );
}
```

---

## 3. Strings — single quotes by default

Single quotes for string literals that contain no variables. Double quotes only when you are
interpolating a variable inside the string. This is not just style — it avoids accidental
variable substitution and makes intent clear.

```php
// ✅ correct
$label  = 'Hero Section';
$handle = "{$slug}-style";   // interpolation needs double quotes
$handle = $slug . '-style';  // concatenation is equally fine outside hook names

// ❌ wrong
$label  = "Hero Section";    // double quotes with no interpolation
```

---

## 4. Spacing — control structures vs. function calls

A single rule covers both: **exactly one space after the opening parenthesis and before the
closing parenthesis** of control structures, function declarations and function calls — the
WordPress "liberal spacing" style. Empty parentheses stay empty: `my_fn()`.

```php
// ✅ correct
if ( $is_active ) {                // space inside parens — control structure
	do_thing( $arg );              // space inside parens — WP function call style
}

foreach ( $items as $key => $val ) {
	process( $key, $val );
}

// ❌ wrong — no spaces inside control structure parens
if($is_active) {
	do_thing($arg);
}
```

**Space after `!`** — keeps negation visible and avoids visual confusion with `!=`:

```php
// ✅ correct
if ( ! $is_configured ) {
	return;
}

// ❌ wrong
if ( !$is_configured ) {
	return;
}
```

**Operator spacing** — spaces on both sides of all logical, arithmetic, comparison, string,
and assignment operators:

```php
// ✅ correct
$total = $price * $qty;
$label = 'Item: ' . $name;

// ❌ wrong
$total=$price*$qty;
```

---

## 5. Yoda conditions and strict comparisons

In a Yoda condition the constant or literal goes on the **left**, the variable on the
**right**. The reason: if you accidentally type `=` instead of `==`, `true = $var` is a parse
error (caught immediately), whereas `$var = true` silently sets the variable to `true` and
evaluates as truthy.

Yoda applies to `==`, `!=`, `===`, `!==`. It is **not recommended** for relational operators
`<` and `>` — those don't have the accidental-assignment risk.

```php
// ✅ correct — constant on the left
if ( false === $visibility ) {
	return;
}

if ( null !== $result ) {
	process( $result );
}

if ( 'draft' === get_post_status( $post_id ) ) {
	return;
}

// ❌ wrong — variable on the left
if ( $visibility === false ) {
	return;
}
```

**Strict comparisons.** Use `===` and `!==`. Loose `==` / `!=` coerce types: `'1' == '01'` and
`100 == '1e2'` are both `true` in PHP, which is almost never the intent.

**No assignments in conditionals.** This is both a correctness rule and a readability rule:

```php
// ✅ correct
$user = get_current_user_id();
if ( 0 !== $user ) {
	load_for_user( $user );
}

// ❌ wrong — assignment inside the conditional
if ( $user = get_current_user_id() ) {
	load_for_user( $user );
}
```

---

## 6. Control flow — braces, `elseif`, ternary

**Mandatory braces.** Curly braces are required for every `if`/`else`/`for`/`while`/`foreach`
block, even single-statement bodies. Brace-free single-liners are easy to break when a second
statement is added later.

```php
// ✅ correct
if ( $is_active ) {
	do_thing();
}

// ❌ wrong — brace-free
if ( $is_active )
	do_thing();
```

**`elseif` not `else if`.** The two-word form is invalid in PHP's alternative/colon syntax
(`if (...): … elseif (…): … endif;`). Use the single-word form everywhere for consistency.

```php
// ✅ correct
if ( 'published' === $status ) {
	show_post();
} elseif ( 'draft' === $status ) {
	show_draft_notice();
} else {
	return;
}

// ❌ wrong
} else if ( 'draft' === $status ) {
```

**Short ternary `?:` is prohibited.** PHP's "Elvis operator" silently evaluates the truthy
branch if the condition is truthy, which makes it easy to return unexpected values. Use the
full ternary or null coalescing instead.

```php
// ✅ correct — full ternary
$label = $name ? $name : 'Default';

// ✅ also correct — null coalescing for "null or missing" check (not prohibited; different operator)
$label = $name ?? 'Default';

// ❌ wrong — short ternary
$label = $name ?: 'Default';
```

---

## 7. Casts and operators

**Lowercase short-form casts.** WPCS requires the compact versions: `(int)`, `(bool)`,
`(float)`, `(string)`. The long forms `(integer)`, `(boolean)`, `(double)` are non-standard
and inconsistent across the codebase.

```php
// ✅ correct
$id    = (int) $raw_id;
$flag  = (bool) $checkbox_value;
$price = (float) $raw_price;

// ❌ wrong
$id   = (integer) $raw_id;
$flag = (boolean) $checkbox_value;
```

**Pre-increment / pre-decrement for standalone statements.** `++$i` is marginally faster in
some PHP versions and is the WPCS convention when the expression's value is not used.

```php
// ✅ correct — pre-increment for standalone statements
++$count;

// acceptable — post-increment inside an expression where the value is consumed
$arr[] = $i++;
```

---

## 8. Naming conventions

| Identifier type | Style | Example |
|---|---|---|
| Variables, functions, hooks | `snake_case` | `$user_id`, `my_theme_register_styles()`, `my_theme_hero_loaded` (hook names use underscores, not slashes or dashes) |
| Classes, traits, interfaces, enums | `Capitalized_Snake_Case` | `My_Theme_Asset_Loader`, `WP_REST_API` (acronyms fully uppercase) |
| Constants | `UPPER_SNAKE_CASE` | `MY_THEME_VERSION`, `WP_DEBUG` |
| PHP function parameters | stable `snake_case` | `function process( string $post_id, bool $is_draft )` (PHP 8.0 named-params are a public API — renaming is a breaking change) |

WP function names and hook names are `snake_case` regardless of modern PHP features — those
come from the WordPress API surface, not from this file.

---

## 9. File organization

**One object per file.** Each PHP file may contain exactly one class, interface, trait, or
enum. This enforces modularity and keeps the blast radius of changes small.

**Class file naming.** Files that define a class use the `class-{slug}.php` naming convention,
where underscores in the class name become hyphens in the filename:

| Class name | Filename |
|---|---|
| `My_Theme_Asset_Loader` | `class-my-theme-asset-loader.php` |
| `WP_REST_My_Endpoint` | `class-wp-rest-my-endpoint.php` |

**General PHP files** (helpers, template tags, config) use lowercase with hyphens:
`template-helpers.php`, `plugin-hooks.php`.

---

## 10. OOP — visibility and modifier order

Explicit `public`, `protected`, or `private` is required on every property, method, and class
constant. The `var` keyword is prohibited — it behaves as `public` but provides no documentation.

**Modifier order** (follow this sequence strictly):

| Construct | Order |
|---|---|
| Classes | `abstract`/`final` → `readonly` |
| Constants | `final` → visibility |
| Properties | visibility → `static`/`readonly` → type declaration |
| Methods | `abstract`/`final` → visibility → `static` |

```php
// ✅ correct
class My_Theme_Block_Loader {
	private static string $version = '1.0.0';

	final public static function get_version(): string {
		return self::$version;
	}
}

// ❌ wrong — visibility missing, modifier order wrong
class My_Theme_Block_Loader {
	static $version = '1.0.0';

	static final function get_version(): string {}
}
```

**Object instantiation** — always include parentheses, even with a no-arg constructor. No
space between the class name and `(`:

```php
// ✅ correct
$loader = new My_Theme_Block_Loader();

// ❌ wrong
$loader = new My_Theme_Block_Loader;
```

---

## 11. Database — `$wpdb->prepare()`

When a direct `$wpdb` query is unavoidable, always use `prepare()` to prevent SQL injection.
Placeholders are automatically quoted — do **not** add manual quotes around them.

| Placeholder | PHP type |
|---|---|
| `%d` | Integer |
| `%f` | Float |
| `%s` | String |
| `%i` | Identifier (table/column name, WP 6.2+) |

```php
// ✅ correct
$row = $wpdb->get_row(
	$wpdb->prepare(
		'SELECT * FROM %i WHERE user_id = %d AND status = %s',
		$wpdb->prefix . 'my_table',
		$user_id,
		$status
	)
);

// ❌ wrong — manual quoting, string interpolation, or sprintf
$row = $wpdb->get_row(
	"SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = '$user_id'"
);
```

Prefer WP's wrapper functions (`get_posts()`, `WP_Query`, etc.) over raw `$wpdb` where they
exist — they handle escaping, caching, and forward compatibility automatically.

---

## 12. Closing PHP tag

**Pure PHP files (`functions.php`, `inc/**/*.php`, `src/**/*.php`):** omit the closing `?>`.
The closing tag after a file's final statement can emit a trailing newline that breaks HTTP
headers. Omitting it is the safe default.

**Mixed PHP pattern files (files that interleave PHP and HTML block markup):** the closing
`?>` is expected and should stay. The docblock-and-markup pattern in `patterns/` uses PHP
tags to switch contexts:

```php
<?php
/**
 * Title: Hero Section
 * Slug: {{THEME_SLUG}}/hero-section
 * …
 */
?>
<!-- wp:group {…} -->
…
<!-- /wp:group -->
```

Removing `?>` here would leave the block markup inside the PHP tag, where it is parsed as PHP
code — a parse error.

---

## 13. Includes — `require_once` without parens

`require_once` and `include_once` are **language constructs**, not functions. Parentheses are
not needed and should be omitted to signal that distinction.

```php
// ✅ correct
require_once get_template_directory() . '/inc/class-asset-loader.php';

// ❌ wrong — parens imply function call
require_once( get_template_directory() . '/inc/class-asset-loader.php' );
```

Use `require_once` (not `include_once`) for files that your code depends on. If the file is
missing, `require_once` throws a `Fatal Error` immediately, preventing a partial execution
state that can cause silent security leaks.

---

## 14. Dynamic hooks — interpolation not concatenation

Dynamic hook names (actions and filters whose name includes a variable) must use **string
interpolation** with curly-brace wrapping, not concatenation. Automated tools (IDE indexers,
grep-based hook finders) can discover interpolated hooks; concatenated names are opaque.

```php
// ✅ correct — interpolation with curly braces
add_filter( "{$post_type}_template", 'my_callback' );
do_action( "{$new_status}_{$post->post_type}", $post );

// ❌ wrong — concatenation
add_filter( $post_type . '_template', 'my_callback' );
do_action( $new_status . '_' . $post->post_type, $post );
```

---

### Hook callbacks are named functions

The WordPress handbook: closures "should not be passed as filter or action callbacks, as removing
these via `remove_action()` / `remove_filter()` is complex". A child theme or plugin can only unhook
a callback it can name, so every `add_action()` / `add_filter()` callback is a named, prefixed
function passed by name:

```php
// ✅ correct — removable with remove_action( 'init', 'my_theme_register_patterns' )
function my_theme_register_patterns(): void {
	// …
}
add_action( 'init', 'my_theme_register_patterns' );

// ❌ wrong — nothing else can remove this
add_action( 'init', static function (): void {
	// …
} );
```

Closures and arrow functions remain fine where no hook is involved — a block's `render_callback`,
`array_map()`, `array_find()`, `usort()`.

---

## 15. Prohibited constructs

These are off-limits regardless of context:

| Construct | Why |
|---|---|
| `extract()` | Silently injects variables into scope — impossible to debug and creates collision vectors |
| `eval()` | Arbitrary code execution; never safe in a plugin/theme context |
| `create_function()` | Deprecated since PHP 7.2; internally uses `eval()` |
| `goto` | Makes control flow untraceable |
| Backtick operator `` ` `` | Executes shell commands — same risk category as `exec()` |
| `/e` regex modifier | Evaluates the replacement string as PHP code — removed in PHP 7.0, still seen in copied snippets |
| `@` error suppression | Masks real errors. The WordPress handbook calls it "highly discouraged"; this skill does not emit it — check the return value instead |

---

## 16. Relationship to architecture.md § PHP Requirements

The PHP Requirements section in `references/architecture.md` (§ "PHP Requirements")
and this file cover complementary concerns:

| Concern | Where it lives |
|---|---|
| Which modern PHP features to use (`match`, `??`, `?->`, `readonly`, typed params, PHP 8.4 features) | `architecture.md → PHP Requirements` |
| What to write instead on PHP 8.2 / 8.3, and how to establish the site's PHP version | `php-compatibility.md` |
| How to format WP PHP (Yoda, `array()`, tabs, naming, visibility, `$wpdb`) | **This file** |

Read both before writing any PHP. The architecture section answers "which PHP features can I use?";
this file answers "how should WordPress PHP look?". They are designed to complement, not
contradict, each other.
