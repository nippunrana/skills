# WP AI Client + Connectors (WP 7.0+)

A provider-agnostic PHP layer for generative AI. Separates *what* you want (prompt and requirements) from *how* it runs (which provider and model). Ships in core in WP 7.0 with OpenAI, Anthropic (Claude), and Google (Gemini) connectors out of the box.

**Load this file when**: the user asks to "call an AI model from PHP", "summarise / translate / classify with AI", "register a custom AI provider", "manage API keys", or implements anything inside an Ability's `execute_callback` that needs an LLM.

---

## 1. Pre-flight: always feature-detect first

Generation methods make network calls and consume credits. Feature-detection methods are **pure capability checks** — no API call, no latency, no cost. Always gate generation behind one.

```php
if ( ! WP_AI_Client_Prompt_Builder::is_supported_for_text_generation() ) {
    return new WP_Error(
        'no_text_provider',
        __( 'No AI provider is configured for text generation.', 'my-plugin' )
    );
}
```

| Method | Returns true when… |
|---|---|
| `is_supported_for_text_generation()` | Any active connector can produce text |
| `is_supported_for_image_generation()` | Any active connector can produce images |
| `is_supported_for_text_to_speech_conversion()` | Any active connector supports TTS |
| `is_supported_for_speech_generation()` | Any active connector supports native speech generation |
| `is_supported_for_video_generation()` | Any active connector supports video |

These are **static methods** on `WP_AI_Client_Prompt_Builder`. Call them at the call site or during admin notice rendering — not at plugin load (the connector registry may not be ready yet; wait until `init` or later).

---

## 2. The prompt builder

Entry point: `wp_ai_client_prompt( $text = '' )` returns a `WP_AI_Client_Prompt_Builder`. Pass the prompt text directly *or* use `with_text()` later — both forms are equivalent.

### Configuration methods

| Concern | Method |
|---|---|
| Prompt text | `with_text( string $text )` |
| File input (image, audio, etc.) | `with_file( string $path_or_url, ?string $mime = null )` |
| Conversation history | `with_history( array $messages )` |
| System instruction | `using_system_instruction( string $text )` |
| Temperature | `using_temperature( float $temp )` |
| Max output tokens | `using_max_tokens( int $max )` |
| Nucleus sampling | `using_top_p( float $p )` |
| Top-k sampling | `using_top_k( int $k )` |
| Stop sequences | `using_stop_sequences( array $sequences )` |
| Preferred model | `using_model_preference( string $model )` |
| Output modalities | `as_output_modalities( array $modalities )` |
| Output file type | `as_output_file_type( string $mime )` |
| Structured JSON response | `as_json_response( array $json_schema )` |

All methods return `$this` and can be chained in any order. Validation happens at the generation call, not at configuration time.

### Generation methods

Two flavours: scalar return (just the payload) or `*_result()` (rich `GenerativeAiResult` wrapper with metadata).

| Modality | Scalar return | Result-form |
|---|---|---|
| Text | `generate_text()` | `generate_text_result()` |
| Image | `generate_image()` | `generate_image_result()` |
| Text-to-speech | `convert_text_to_speech()` | `convert_text_to_speech_result()` |
| Native speech | `generate_speech()` | `generate_speech_result()` |
| Video | `generate_video()` | `generate_video_result()` |

Every generation method can return `WP_Error`. Always check with `is_wp_error()` before using the result.

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
    error_log( 'AI summary failed: ' . $summary->get_error_message() );
    return '';
}
return $summary;
```

### Structured JSON response

Use `as_json_response()` with a JSON schema when you need the model to return data your code can parse without regex.

```php
$result = wp_ai_client_prompt( 'Summarize this post and estimate reading time in minutes.' )
    ->as_json_response( array(
        'type'       => 'object',
        'properties' => array(
            'summary'      => array( 'type' => 'string' ),
            'reading_time' => array( 'type' => 'integer' ),
        ),
        'required'   => array( 'summary', 'reading_time' ),
    ) )
    ->generate_text();
```

The model is constrained to emit valid JSON matching the schema. You still need to `json_decode()` the result.

### Token usage tracking

Use the `*_result()` form to access usage data:

```php
$result = wp_ai_client_prompt( 'Translate to French: Hello, world.' )
    ->generate_text_result();

if ( is_wp_error( $result ) ) {
    return $result;
}

$usage = $result->getTokenUsage();      // returns object with input/output/thinking counts
$provider = $result->getProviderMetadata(); // which provider actually served the request
$model    = $result->getModelMetadata();    // which model was used

log_ai_spend( $usage->input, $usage->output, $provider->name, $model->id );
```

### Image generation

```php
$image_path = wp_ai_client_prompt( 'A watercolour illustration of a fox in autumn leaves.' )
    ->as_output_file_type( 'image/png' )
    ->generate_image();
```

---

## 4. Connectors API

Connectors are the discovery/auth layer. Registered connectors automatically appear in **Settings → Connectors** for the site admin.

### Registering or modifying connectors

Use the `wp_connectors_init` action; it fires once with a `WP_Connector_Registry` instance.

```php
add_action( 'wp_connectors_init', function ( WP_Connector_Registry $registry ) {
    if ( $registry->is_registered( 'openai' ) ) {
        $connector = $registry->unregister( 'openai' );
        $connector['description'] = __( 'Internal OpenAI proxy (rate-limited per team).', 'my-plugin' );
        $registry->register( 'openai', $connector );
    }
} );
```

`WP_Connector_Registry` methods:
- `is_registered( string $id ): bool`
- `register( string $id, array $args ): void`
- `unregister( string $id ): array` — returns the unregistered definition (handy for modify-and-re-register patterns)

### Authentication

Two auth methods are supported: `api_key` and `none`. Most providers use `api_key`.

### Credential resolution priority

The Connectors layer resolves the API key for `api_key` connectors in this **strict order**, first match wins:

1. **Environment variable** — e.g. `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GOOGLE_API_KEY`. Set in your server's environment or `.env` file loaded by your stack.
2. **PHP constant** — `define( 'OPENAI_API_KEY', '…' );` typically in `wp-config.php`.
3. **Database option** — set by the admin at **Settings → Connectors**. Stored under the general pattern `connectors_{type}_{id}_api_key`. In WP 7.0 the type segment is always `ai`, so in practice the key is `connectors_ai_{$id}_api_key` (e.g. `connectors_ai_openai_api_key`). The `{type}` placeholder is reserved for future connector categories beyond AI.

The admin screen shows which source supplied the active key (env / constant / DB) per connector. Database keys are masked in the REST API to prevent leakage.

**Where to set keys, by environment**

| Environment | Recommended source | Why |
|---|---|---|
| Local dev | env var via `.env` or shell | Not committed; per-developer |
| Staging | PHP constant in environment-specific config | Loaded with `wp-config-staging.php` |
| Production | env var (managed secret) | Secret manager integration; no DB exposure |
| Demo / client-managed | DB via Settings → Connectors UI | Non-technical user can rotate keys |

### Third-party providers

Plugins can register their own connector inside `wp_connectors_init` by calling `$registry->register( 'my-provider', [...] )` with a definition that includes the auth method, capability flags, and an SDK adapter class. The new provider then becomes available to every `wp_ai_client_prompt()` call.

---

## 5. Common pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| `WP_Error: no_active_connector` | No connector configured at Settings → Connectors | Surface the message to the admin; do not retry |
| Always falls back to one provider | `using_model_preference()` requested a model none of the active connectors support | Drop the preference or add a matching connector |
| JSON response not parseable | Forgot `as_json_response()` | Add it with a schema; the model will then be constrained |
| Token usage missing | Called `generate_text()` instead of `generate_text_result()` | Use the `_result()` variant when you need metadata |
| Feature detection returns false in plugin file | Called too early — registry not built | Move the check inside an `init` (or later) hook |
| API key not picked up | Env var set after PHP-FPM started | Restart PHP-FPM; env vars are read at process start |
