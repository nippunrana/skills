# PHP Version Compatibility

This skill writes for **PHP 8.4 by default**. This file says how to find out which PHP version a site really runs, how to declare it, and what to write instead when the site is on **8.3 or 8.2**.

**Load this file when**: the site's PHP version is below 8.4, is unknown, or the user asks which PHP features a theme or plugin may use.

**Supported range:** PHP 8.2, 8.3, 8.4 and 8.5. WordPress 7.0 and 7.1 run on all four. PHP 8.1 and older no longer receive security fixes from the PHP project — a site on one of them is out of scope: say so and recommend upgrading before writing code for it. Check php.net/supported-versions for current end-of-life dates (8.2's security support ends 31 December 2026).

---

## 1. Establish the PHP version — never assume it

PHP 8.4 syntax on a PHP 8.2 server is a **parse error that takes the whole site down**, so the version must be known before writing a class or signature that depends on it. Use the first source that answers:

1. **The project's context file** — a line such as `PHP version: 8.4`.
2. **The running site** — `php -v` in the site's environment, or WordPress admin → Tools → Site Health → Info → Server → "PHP version".
3. **Project configuration** — `composer.json` (`config.platform.php`, then `require.php`), the `phpVersion` key of `.wp-env.json`, a container image tag (`php:8.3-fpm`, `wordpress:php8.4`), or a CI matrix.
4. **The theme's own declaration** — the `Requires PHP:` header in `style.css`. This is a declared minimum, not proof of what the server runs.
5. **Ask the user.**

If none of these gives an answer, **do not write 8.4-only syntax**. Write to the 8.2 column of §3 (that code runs unchanged on 8.2 through 8.5), declare `Requires PHP: 8.2`, and say in your reply that the version was unknown and which assumption you made.

**Record what you find.** When the project has a context file, add or update two lines in its environment section — `PHP version:` and `WordPress version:` — so the next session does not have to rediscover them. When the project has no context file, tell the user the versions you found and offer to record them; do not create the file unasked.

---

## 2. Declare the minimum, so WordPress enforces it

Put the versions in the theme's `style.css` header (for a plugin, the main file's header):

```css
/*
Theme Name:        {{THEME_NAME}}
Text Domain:       {{TEXT_DOMAIN}}
Version:           1.0.0
Requires at least: 7.0
Requires PHP:      8.4
*/
```

WordPress checks both headers when the theme is activated and refuses to switch to it on an older PHP or WordPress. Declare the version the code was written for: `8.4` on a site confirmed at 8.4 or newer, the site's own version on 8.3 or 8.2, and `8.2` when the version could not be established.

---

## 3. What to write on each version

**Rule of use:** these are tools, not a quota. Use a feature where it removes code or prevents a bug. Pattern files are markup and gain nothing from them.

### Needs PHP 8.4

| Feature | 8.4 | On 8.3 / 8.2 write |
|---|---|---|
| Property hooks | `public string $slug { set => sanitize_title( $value ); }` | A private property with `get_*()` / `set_*()` methods |
| ↳ tooling caveat | PHP_CodeSniffer (and therefore the WordPress Coding Standards ruleset) cannot parse property hooks yet. If the project has a `phpcs.xml` / `phpcs.xml.dist` or a lint script that runs `phpcs`, use the method form even on 8.4. For asymmetric visibility and chaining on `new`, the tool's tokenizer has support, but not every rule is confirmed to handle them — run the project's linter on the file before relying on them. | — |
| Asymmetric visibility | `public private(set) array $registered = array();` | `private array $registered` plus a getter (or `public readonly` when it is written once) |
| Chaining on `new` | `new My_Theme_Loader()->register();` | `( new My_Theme_Loader() )->register();` |
| `#[\Deprecated]` attribute | Marks a function, method or class constant | Leave it off — it has no effect before 8.4. On every version, WordPress code calls `_deprecated_function()`. |
| `mb_trim()`, `mb_ltrim()`, `mb_rtrim()`, `mb_ucfirst()`, `mb_lcfirst()` | Available | `preg_replace( '/^\s+|\s+$/u', '', $text )`; `mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 )` |

### Needs PHP 8.3

| Feature | 8.3+ | On 8.2 write |
|---|---|---|
| Typed class constants | `public const string VERSION = '1.0.0';` | `public const VERSION = '1.0.0';` |
| `#[\Override]` | On a method that must override a parent's | Leave it off — it has no effect on 8.2 |
| `json_validate()` | `json_validate( $json )` | `json_decode( $json );` then test `JSON_ERROR_NONE === json_last_error()` |
| Dynamic class constant fetch | `My_Theme_Icons::{$name}` | `constant( My_Theme_Icons::class . '::' . $name )` |

### Safe on every supported version (8.2+)

`readonly` properties and classes, enums, `match`, the nullsafe operator `?->`, constructor property promotion, union / intersection / DNF types, the `never`, `true`, `false` and `null` types, first-class callable syntax, `new` in initializers, `#[\SensitiveParameter]` (use it on any parameter that carries an API key or password), `str_contains()`, `str_starts_with()`, `str_ends_with()`, `array_is_list()`.

### Polyfilled by WordPress — use on any PHP version

WordPress defines these when PHP does not, so they are safe on every site this skill targets:

| Functions | Native since | Polyfilled since |
|---|---|---|
| `array_find()`, `array_find_key()`, `array_any()`, `array_all()` | PHP 8.4 | WordPress 6.8 |
| `array_first()`, `array_last()` | PHP 8.5 | WordPress 6.9 |

```php
$sticky = array_find(
	$posts,
	static fn( WP_Post $post ): bool => is_sticky( $post->ID )
);
```

### The same class on 8.4 and on 8.2

```php
// PHP 8.4
final class My_Theme_Asset_Registry {
	public const string VERSION = '1.0.0';

	/** @var array<string, string> Handle => URL. Readable anywhere, writable only inside the class. */
	public private(set) array $registered = array();

	public function register( string $handle, string $src ): void {
		$this->registered[ $handle ] = $src;
	}
}
```

```php
// PHP 8.2
final class My_Theme_Asset_Registry {
	public const VERSION = '1.0.0';

	/** @var array<string, string> Handle => URL. */
	private array $registered = array();

	public function register( string $handle, string $src ): void {
		$this->registered[ $handle ] = $src;
	}

	/** @return array<string, string> */
	public function get_registered(): array {
		return $this->registered;
	}
}
```

---

## 4. Never write these, on any version

Each is deprecated somewhere between 8.2 and 8.5. Code that avoids them runs without deprecation notices across the whole supported range.

| Do not write | Deprecated in | Write instead |
|---|---|---|
| `function f( string $text = null )` — implicitly nullable parameter | 8.4 | `function f( ?string $text = null )` |
| A property that is never declared (`$this->cache = …` on a class without `$cache`) | 8.2 | Declare every property |
| `"${name}"` string interpolation | 8.2 | `"{$name}"` |
| `get_class()` / `get_parent_class()` with no argument | 8.3 | `static::class`, `parent::class` |
| `trigger_error( $message, E_USER_ERROR )` | 8.4 | Return a `WP_Error`, or throw an exception |
| The `E_STRICT` constant | 8.4 | Remove it |
| `fputcsv()` / `fgetcsv()` / `str_getcsv()` without the escape argument | 8.4 | Pass the escape argument explicitly |
| `(boolean)`, `(integer)`, `(double)`, `(binary)` casts | 8.5 | `(bool)`, `(int)`, `(float)`, `(string)` |
| The backtick operator; `case 1;` (semicolon after a case) | 8.5 | `shell_exec()` is off-limits in a theme anyway; `case 1:` |
| `null` as an array key | 8.5 | An empty string, or check for `null` first |
| `__sleep()` / `__wakeup()` | 8.5 | `__serialize()` / `__unserialize()` |

---

## 5. PHP 8.5-only syntax is out of scope

A floor of 8.4 means the code must run on 8.4. Do not emit the pipe operator (`|>`), `clone()` with property overrides, `#[\NoDiscard]`, closures in constant expressions, or static asymmetric visibility — they are parse errors or missing on 8.4. (`array_first()` / `array_last()` are the exception: WordPress polyfills them.)
