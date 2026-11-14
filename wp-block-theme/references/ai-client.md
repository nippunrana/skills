# WP AI Client + Connectors (WordPress 7.0+)

A provider-agnostic PHP layer for generative AI. It separates *what* you want (the prompt and its requirements) from *how* it runs (which provider and model).

**Core ships the client, not the providers.** WordPress bundles no AI provider. A site can generate nothing until a provider plugin is installed and given credentials. The three official plugins are *AI Provider for Anthropic*, *AI Provider for Google* and *AI Provider for OpenAI*; the Connectors screen lists them and offers to install them. Never assume AI works because the site runs 7.0.

**Load this file when**: the user asks to call an AI model from PHP, summarise / translate / classify / generate with AI, manage provider API keys, register a connector, or let a model call an ability.

---

## 1. Pre-flight: feature-detect before offering a feature

Feature detection is an **instance** call on a configured builder. It matches the builder's configuration against the models the installed providers offer; it makes no API call and costs nothing. Use it to decide whether to show or enable an AI feature. It does not replace error handling: a generating call can still fail, so every example below checks `is_wp_error()` — with or without a support check first.

```php
$builder = wp_ai_client_prompt( $prompt_text )
	->using_temperature( 0.4 );

if ( ! $builder->is_supported_for_text_generation() ) {
	return new WP_Error(
		'my_plugin_no_text_provider',
		__( 'No AI provider is available for text generation.', 'my-plugin' )
	);
}

$text = $builder->generate_text();
```

| Method | Returns true when… |
|---|---|
| `is_supported_for_text_generation()` | A configured provider can produce text for this prompt |
| `is_supported_for_image_generation()` | …images |
| `is_supported_for_text_to_speech_conversion()` | …text-to-speech |
| `is_supported_for_speech_generation()` | …native speech |
| `is_supported_for_video_generation()` | …video |

**Never** write `WP_AI_Client_Prompt_Builder::is_supported_for_text_generation()`. The class has no static methods of that name and the call is a fatal error.

Call these only after the `init` action has finished — providers register and API keys reach the client during `init` (up to priority 20). Safe places: `wp_loaded`, `admin_init`, a REST or render callback, an ability's `execute_callback`.

**`wp_supports_ai(): bool`** is a different question: *has AI been switched off on this site?* It returns `false` when the constant `WP_AI_SUPPORT` is defined falsy or when the `wp_supports_ai` filter returns `false`, and `true` otherwise. Use it to decide whether to register an AI feature at all; use `is_supported_for_*()` to decide whether a specific request can run. When `wp_supports_ai()` is `false`, every support check returns `false` and every generating call returns `WP_Error`.

---

## 2. The prompt builder

`wp_ai_client_prompt( $prompt = null )` returns a `WP_AI_Client_Prompt_Builder`. Pass the prompt text directly or add it with `with_text()`.

The builder wraps the PHP AI Client SDK: any snake_case method is forwarded to the SDK's camelCase method of the same name. Exceptions are caught and surface as a `WP_Error` from the generating call, so the chain never throws.

### Configuration methods

| Concern | Method |
|---|---|
| Prompt text | `with_text( string $text )` |
| File input (image, audio, document) | `with_file( $file, ?string $mime_type = null )` |
| Conversation history | `with_history( Message ...$messages )` — SDK `Message` objects, variadic |
| System instruction | `using_system_instruction( string $text )` |
| Temperature | `using_temperature( float $temperature )` |
| Max output tokens | `using_max_tokens( int $max )` |
| Nucleus / top-k sampling | `using_top_p( float $p )`, `using_top_k( int $k )` |
| Stop sequences | `using_stop_sequences( string ...$sequences )` — variadic |
| Preferred models, in order | `using_model_preference( ...$models )` — variadic; a preference, not a requirement |
| Force a provider | `using_provider( string $provider_id )` |
| Number of candidates | `using_candidate_count( int $count )` |
| Abilities the model may call | `using_abilities( ...$abilities )` — see §6 |
| Output MIME type | `as_output_mime_type( string $mime_type )` |
| Structured JSON response | `as_json_response( ?array $schema = null )` |

`as_output_file_type()` and `as_output_modalities()` take SDK enum objects, not strings. To ask for a PNG, use `as_output_mime_type( 'image/png' )`.

All configuration methods return the builder and can be chained in any order.

### Generation methods

Every generating method returns its value **or `WP_Error`** — always check `is_wp_error()`.

| Modality | Single | Multiple candidates | Full result object |
|---|---|---|---|
| Text | `generate_text()` → `string` | `generate_texts( ?int $count )` → `string[]` | `generate_text_result()` |
| Image | `generate_image()` → `File` | `generate_images( ?int $count )` → `File[]` | `generate_image_result()` |
| Text-to-speech | `convert_text_to_speech()` → `File` | `convert_text_to_speeches( ?int $count )` | `convert_text_to_speech_result()` |
| Native speech | `generate_speech()` → `File` | `generate_speeches( ?int $count )` | `generate_speech_result()` |
| Video | `generate_video()` → `File` | `generate_videos( ?int $count )` | `generate_video_result()` |

A `File` exposes `getDataUri()`, `getBase64Data()`, `getUrl()` and `getMimeType()`.

The `*_result()` methods return a `GenerativeAiResult`:

| Method | Returns |
|---|---|
| `toText()`, `toFile()`, `toImageFile()` | The generated content |
| `toMessage()` | The model's reply as an SDK `Message` (needed for ability calls, §6) |
| `getTokenUsage()` | `TokenUsage`: `getPromptTokens()`, `getCompletionTokens()`, `getTotalTokens()`, `getThoughtTokens()` |
| `getProviderMetadata()` | `getId()`, `getName()` |
| `getModelMetadata()` | `getId()`, `getName()` |

### Error codes

`prompt_prevented` (blocked by `wp_supports_ai()` or the filter in §5), `prompt_network_error`, `prompt_client_error`, `prompt_upstream_server_error`, `prompt_token_limit_reached`, `prompt_invalid_argument`, `prompt_builder_error`. Each carries an HTTP `status` in its error data, so the `WP_Error` can be returned straight from a REST callback or an ability.

---

## 3. Worked examples

### Plain text

```php
$summary = wp_ai_client_prompt()
	->using_system_instruction( 'You are a concise editor. One paragraph max.' )
	->using_temperature( 0.3 )
	->using_max_tokens( 200 )
	->with_text( "Summarise this post:\n\n" . wp_strip_all_tags( $post->post_content ) )
	->generate_text();

if ( is_wp_error( $summary ) ) {
	return $summary;
}
```

### Structured JSON response

```php
$json = wp_ai_client_prompt( 'Summarise this post and estimate reading time in minutes.' )
	->as_json_response(
		array(
			'type'       => 'object',
			'properties' => array(
				'summary'      => array( 'type' => 'string' ),
				'reading_time' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'summary', 'reading_time' ),
		)
	)
	->generate_text();

if ( is_wp_error( $json ) ) {
	return $json;
}

$data = json_decode( $json, true );
```

### Token usage

```php
$result = wp_ai_client_prompt( 'Translate to French: Hello, world.' )
	->generate_text_result();

if ( is_wp_error( $result ) ) {
	return $result;
}

$text   = $result->toText();
$usage  = $result->getTokenUsage();
$tokens = $usage->getPromptTokens() + $usage->getCompletionTokens();
$model  = $result->getModelMetadata()->getId();
```

### Image generation

```php
$image = wp_ai_client_prompt( 'A watercolour illustration of a fox in autumn leaves.' )
	->as_output_mime_type( 'image/png' )
	->generate_image();

if ( ! is_wp_error( $image ) ) {
	$data_uri = $image->getDataUri();
}
```

---

## 4. Connectors API

A connector is the admin-facing record for a service and its credentials. Connectors appear at **Settings → Connectors**.

### How AI connectors come to exist

Core registers placeholder connectors for `anthropic`, `google` and `openai` (each pointing at its provider plugin). When a provider plugin registers its provider class with the AI Client's provider registry, core creates or completes that connector automatically. **A plugin that only wants to use AI never registers a connector and never handles an API key.**

An AI provider plugin registers its provider on `init`, before priority 15:

```php
/**
 * Registers the provider with the AI Client before connectors are built.
 */
function my_plugin_register_ai_provider(): void {
	\WordPress\AiClient\AiClient::defaultRegistry()->registerProvider( My_Provider::class );
}
add_action( 'init', 'my_plugin_register_ai_provider', 5 );
```

`My_Provider` must implement the PHP AI Client SDK's provider interface — writing one is outside this file's scope.

### Reading connectors

Available after `init`:

- `wp_is_connector_registered( string $id ): bool`
- `wp_get_connector( string $id ): ?array`
- `wp_get_connectors(): array`

### Overriding metadata or adding a non-AI connector

`wp_connectors_init` fires once with the `WP_Connector_Registry`, after core and auto-discovered connectors are registered. To change an existing connector, unregister it and register it again:

```php
/**
 * Replaces the description shown for the OpenAI connector.
 */
function my_plugin_override_openai_connector( WP_Connector_Registry $registry ): void {
	if ( $registry->is_registered( 'openai' ) ) {
		$connector                = $registry->unregister( 'openai' );
		$connector['description'] = __( 'Internal OpenAI proxy (rate-limited per team).', 'my-plugin' );
		$registry->register( 'openai', $connector );
	}
}
add_action( 'wp_connectors_init', 'my_plugin_override_openai_connector' );
```

`register( string $id, array $args ): ?array` accepts `name` (required), `type` (required — AI providers use `ai_provider`, with an underscore), `description`, `logo_url`, `authentication` (`method`: `api_key` or `none`; optional `credentials_url`, `setting_name`, `constant_name`, `env_var_name`) and `plugin` (`file`, `is_active`). Registering a connector does **not** create an AI provider.

### Credential resolution

For an `api_key` connector the key is looked up in this order; the first non-empty value wins:

1. **Environment variable** — AI providers use `{PROVIDER_ID}_API_KEY`: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GOOGLE_API_KEY`.
2. **PHP constant** of the same name, typically in `wp-config.php`.
3. **Database option** saved from the Connectors screen. For AI providers the option is `connectors_ai_{id}_api_key`, e.g. `connectors_ai_openai_api_key`.

The screen shows which source supplied the active key and masks stored keys. Never read or echo a key yourself; let the client resolve it.

| Environment | Recommended source | Why |
|---|---|---|
| Local dev | Environment variable | Not committed; per developer |
| Production | Environment variable or constant from a secret manager | No key in the database |
| Client-managed | Connectors screen | A non-technical user can rotate it |

---

## 5. Governance: switch-off, cost control, timeouts

| Lever | Use |
|---|---|
| Constant `WP_AI_SUPPORT` | `define( 'WP_AI_SUPPORT', false );` turns AI off for the whole site. |
| Filter `wp_supports_ai` | Turn AI off per request. |
| Filter `wp_ai_client_prevent_prompt` | `( bool $prevent, WP_AI_Client_Prompt_Builder $builder )`. Return `true` to block a prompt. The builder passed in is a clone. A blocked generating call returns `WP_Error` (`prompt_prevented`); a blocked support check returns `false`. |
| Filter `wp_ai_client_default_request_timeout` | Default HTTP timeout in seconds (`float`, default `30.0`). |

```php
// Hard budget cap: stop new prompts once today's spend is exceeded.
/**
 * Blocks new prompts once the daily AI budget is spent.
 */
function my_plugin_enforce_ai_budget( bool $prevent ): bool {
	return $prevent || (bool) get_transient( 'my_plugin_ai_budget_exceeded' );
}
add_filter( 'wp_ai_client_prevent_prompt', 'my_plugin_enforce_ai_budget' );
```

---

## 6. Letting the model call abilities

Register the capability as an ability first (`references/abilities-api.md`), then hand it to the builder. `using_abilities()` accepts ability names or `WP_Ability` objects and declares each one to the model as a callable function.

```php
$abilities = array( 'my-plugin/get-order-status' );

$result = wp_ai_client_prompt( $question )
	->using_abilities( ...$abilities )
	->generate_text_result();

if ( is_wp_error( $result ) ) {
	return $result;
}

$resolver = new WP_AI_Client_Ability_Function_Resolver( ...$abilities );
$reply    = $result->toMessage();

if ( $resolver->has_ability_calls( $reply ) ) {
	// Runs each requested ability (permission and schema checks included)
	// and returns a Message holding the function responses.
	$responses = $resolver->execute_abilities( $reply );
}
```

The resolver executes only the abilities passed to its constructor, so the model cannot reach anything else. To let the model finish its answer, send the earlier messages and `$responses` back in a follow-up prompt with `with_history()`.

The model decides whether to call an ability; a plain text answer with no ability call is a normal outcome.

---

## 7. Common pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Fatal "Call to undefined method" on a support check | Called statically (`::`) | Call it on the builder instance (`->`) |
| Support checks always `false` | No provider plugin installed, no key configured, or `wp_supports_ai()` is `false` | Install a provider plugin and add a key at Settings → Connectors |
| `TypeError` from `as_output_file_type()` | Passed a MIME string | Use `as_output_mime_type()` |
| JSON response not parseable | Forgot `as_json_response()` | Add it with a schema, then `json_decode()` |
| No token usage | Called `generate_text()` | Use `generate_text_result()` and `getTokenUsage()` |
| Model preference ignored | No configured provider offers that model | Expected — the client falls back to a compatible model |
| API key not picked up from the environment | Variable set after PHP started | Restart the PHP process |
