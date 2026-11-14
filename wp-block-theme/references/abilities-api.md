# Abilities API — Server and Client

An **ability** is a named, permission-checked callback with a JSON Schema contract. Registered once, it can be discovered and run from PHP, from the REST API, from JavaScript, and by an AI model (`references/ai-client.md → §6`).

The PHP API has been in core since WordPress 6.9. WordPress 7.0 adds the JavaScript packages. WordPress 7.1 adds the changes in §7.

**Load this file when**: the user wants to register an ability (PHP or JS), expose one over REST or to JavaScript, or needs to know how an ability becomes an HTTP endpoint.

---

## 1. Registering on the server

Two hooks, in this order. A category must exist before an ability names it; an ability whose category is missing is rejected.

```php
// Step 1: the category. Slug: lowercase letters, digits and dashes only — no slash.
/**
 * Registers the theme's ability category.
 */
function {{THEME_PREFIX}}_register_ability_categories(): void {
	wp_register_ability_category(
		'{{THEME_SLUG}}-editorial',
		array(
			'label'       => __( 'Editorial Tools', '{{TEXT_DOMAIN}}' ),
			'description' => __( 'Writing helpers provided by this theme.', '{{TEXT_DOMAIN}}' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', '{{THEME_PREFIX}}_register_ability_categories' );

// Step 2: the ability. Name: namespace/ability-name.
/**
 * Registers the theme's abilities.
 */
function {{THEME_PREFIX}}_register_abilities(): void {
	wp_register_ability(
		'{{THEME_SLUG}}/tone-shift',
		array(
			'label'               => __( 'Tone Shift', '{{TEXT_DOMAIN}}' ),
			'description'         => __( 'Rewrites the given copy in a chosen tone.', '{{TEXT_DOMAIN}}' ),
			'category'            => '{{THEME_SLUG}}-editorial',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'text' => array( 'type' => 'string' ),
					'tone' => array(
						'type' => 'string',
						'enum' => array( 'scientific', 'casual', 'formal' ),
					),
				),
				'required'   => array( 'text', 'tone' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'rewritten' => array( 'type' => 'string' ),
				),
			),
			'permission_callback' => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
			'execute_callback'    => static function ( array $input ): array|WP_Error {
				$rewritten = wp_ai_client_prompt()
					->using_system_instruction( 'You are an editorial assistant.' )
					->with_text( sprintf( "Rewrite this text in a %s tone:\n\n%s", $input['tone'], $input['text'] ) )
					->generate_text();

				if ( is_wp_error( $rewritten ) ) {
					return $rewritten;
				}

				return array( 'rewritten' => $rewritten );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		)
	);
}
add_action( 'wp_abilities_api_init', '{{THEME_PREFIX}}_register_abilities' );
```

### Rules

| Item | Rule |
|---|---|
| Ability name | `namespace/ability-name`; lowercase letters, digits, dashes, one slash. |
| Category slug | Lowercase letters, digits, dashes. **No slash.** `my-theme/editorial` is rejected; use `my-theme-editorial`. |
| Required args | `label`, `description`, `category`, `execute_callback`, `permission_callback`. |
| Optional args | `input_schema`, `output_schema`, `meta` (`show_in_rest`, `annotations`), `ability_class`. |
| Schemas | JSON Schema draft-04, as elsewhere in the WordPress REST API. |
| Hooks | Categories on `wp_abilities_api_categories_init`, abilities on `wp_abilities_api_init`. Registering anywhere else is rejected. |
| Return value | `execute_callback` returns the result or a `WP_Error`. The result is validated against `output_schema`. |

### Running an ability from PHP

```php
$ability = wp_get_ability( '{{THEME_SLUG}}/tone-shift' );
$result  = $ability ? $ability->execute( array( 'text' => $text, 'tone' => 'formal' ) ) : null;
```

`execute()` validates the input, runs the permission callback, runs the callback, and validates the output. It returns the result or a `WP_Error`. `wp_has_ability()` and `wp_get_abilities()` query the registry.

---

## 2. REST exposure

An ability is **not** exposed over REST by default. Set `'meta' => array( 'show_in_rest' => true )` to expose it. This is also what makes it visible to JavaScript.

| Route | Purpose |
|---|---|
| `wp-abilities/v1/abilities` | List exposed abilities |
| `wp-abilities/v1/abilities/{namespace}/{name}/run` | Run one |
| `wp-abilities/v1/categories` | List categories |

The HTTP method for `/run` is derived from `meta.annotations`:

| Annotations | Required method |
|---|---|
| `readonly: true` | `GET` |
| `destructive: true` **and** `idempotent: true` | `DELETE` |
| Anything else | `POST` |

`readonly` is checked first. Calling `/run` with the wrong method returns **405** (`rest_ability_invalid_method`).

| Use case | `readonly` | `destructive` | `idempotent` | HTTP |
|---|---|---|---|---|
| Search posts | `true` | `false` | — | GET |
| Generate a draft | `false` | `false` | `false` | POST |
| Send an email | `false` | `false` | `false` | POST |
| Rebuild a cache index | `false` | `false` | `true` | POST |
| Delete a record | `false` | `true` | `true` | DELETE |

Annotate honestly: AI agents and clients read these flags to decide whether to ask for confirmation.

---

## 3. Client side: two packages

Both are **script modules**, and your own file should be one too.

| Package | Role |
|---|---|
| `@wordpress/abilities` | The store: register, query and execute abilities in the browser. |
| `@wordpress/core-abilities` | Fetches the server's REST-exposed abilities and categories and adds them to the same store. Depends on `@wordpress/abilities`. |

A script module can be imported by name only when something that is enqueued lists it as a **dependency**. Enqueueing `@wordpress/abilities` on its own prints it but leaves it out of the import map, so an `import` of that name then fails to resolve. Register your file as a script module and declare what it imports:

```php
/**
 * Loads the plugin's abilities script in wp-admin.
 */
function my_plugin_enqueue_abilities(): void {
	// Classic scripts the two packages and this file read through window.wp.
	// Script modules do not load them.
	wp_enqueue_script( 'wp-data' );
	wp_enqueue_script( 'wp-i18n' );
	wp_enqueue_script( 'wp-api-fetch' );
	wp_enqueue_script( 'wp-url' );

	wp_enqueue_script_module(
		'my-plugin/abilities',
		plugins_url( 'assets/js/abilities.js', __FILE__ ),
		array( '@wordpress/abilities', '@wordpress/core-abilities' ),
		'1.0.0'
	);
}
add_action( 'admin_enqueue_scripts', 'my_plugin_enqueue_abilities' );
```

A dependency runs only when a file imports it. Import `@wordpress/core-abilities` in your file when it needs the server's abilities (§4); leave it out of both the dependency array and the file when the page only uses its own.

---

## 4. `registerAbility` shape

```js
import { registerAbilityCategory, registerAbility } from '@wordpress/abilities';

const { apiFetch } = window.wp;

registerAbilityCategory( 'my-plugin-maintenance', {
	label: 'Maintenance',
	description: 'Maintenance actions provided by My Plugin',
} );

registerAbility( {
	name: 'my-plugin/delete-log',
	label: 'Delete Log',
	description: 'Permanently delete a server log entry.',
	category: 'my-plugin-maintenance',
	input_schema: {
		type: 'object',
		properties: { id: { type: 'number' } },
		required: [ 'id' ],
	},
	output_schema: {
		type: 'object',
		properties: { deleted: { type: 'boolean' } },
	},
	permissionCallback: () => canManageLogs,
	callback: async ( { id } ) => {
		await apiFetch( { path: `/my-plugin/v1/logs/${ id }`, method: 'DELETE' } );
		return { deleted: true };
	},
	meta: {
		annotations: { readonly: false, destructive: true, idempotent: true },
	},
} );
```

This is `assets/js/abilities.js`, the script module registered in §3. `canManageLogs` stands for a boolean your page already holds (for example a value your PHP passed to the script). `apiFetch` comes from the classic `wp-api-fetch` script enqueued in §3: `@wordpress/api-fetch` is not a script module, so a module cannot import it by name.

| Field | Constraint |
|---|---|
| `name` | Namespaced, lowercase letters, digits, dashes. Unique in the registry. |
| `category` | Must already be registered. Client categories: `registerAbilityCategory( slug, { label, description } )`. Server categories arrive with `@wordpress/core-abilities`, asynchronously — before registering against one, `import { ready } from '@wordpress/core-abilities';` and `await ready;`. Same slug rule as PHP: no slash. |
| `input_schema` / `output_schema` | Snake_case keys, JSON Schema. Input is validated before the callback, output after. |
| `permissionCallback` | Camel-case. Returns a boolean or a promise of one. Runs before `callback`. |
| `callback` | May be async. |
| `meta.annotations` | `readonly`, `destructive`, `idempotent`. |

Run any registered ability — client or server — with `executeAbility( name, input )`, which returns a promise.

---

## 5. Permission callbacks: two layers

PHP uses snake_case `permission_callback`; JavaScript uses camel-case `permissionCallback`. For a server-registered ability the PHP callback is the authoritative gate — it runs on every REST call. A client-side check is UX only (hiding a button); never rely on it for security.

---

## 6. Reading abilities in React

```js
import { useSelect } from '@wordpress/data';
import { store as abilitiesStore } from '@wordpress/abilities';

function MyAbilitiesPicker() {
	const abilities = useSelect(
		( select ) => select( abilitiesStore ).getAbilities( { category: 'my-plugin-maintenance' } ),
		[]
	);
	// …
}
```

Server abilities appear in the store once `@wordpress/core-abilities` has loaded; no manual `fetch()` is needed.

As written, this component needs a build step: `@wordpress/data` is a classic script (`wp-data`), not a script module, so a browser cannot resolve that `import` on its own. Without a build, take `useSelect` from the `wp.data` global and import `store` from `@wordpress/abilities` in a script module set up as in §3.

---

## 7. WordPress 7.1 additions

Use these only when the site runs 7.1 or later.

| Addition | Notes |
|---|---|
| `meta.public` | One exposure flag for every client. `show_in_rest` now defaults to the value of `public`; an explicit `show_in_rest` still wins. `show_in_rest => true` keeps working, so code written for 7.0 needs no change. |
| `wp_get_abilities( $args )` | Accepts filters such as `category` and `namespace`. On 7.0 it takes no arguments and returns everything. |
| Execution filters | `wp_pre_execute_ability`, `wp_ability_normalize_input`, `wp_ability_permission_result`, `wp_ability_execute_result`. |

---

## 8. Common pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Ability never registers | Category slug contains a slash, or category registered on the wrong hook | Dashes only; register it on `wp_abilities_api_categories_init` |
| Ability missing from REST and from JavaScript | `meta.show_in_rest` not set | Set it to `true` |
| `import` of `@wordpress/abilities` fails to resolve | The package was enqueued, not declared as a dependency | List it in your script module's dependency array (§3) |
| `/run` returns 405 | Method does not match the annotations | Use the method from the table in §2 |
| Schema rejected | Newer JSON Schema dialect | Rewrite to draft-04 |
| Agent runs a destructive ability without asking | Missing `destructive: true` | Annotate honestly |
