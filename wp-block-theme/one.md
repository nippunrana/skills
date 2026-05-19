WordPress 7.0 Technical Development Knowledge Base

This documentation provides an authoritative technical overview of the APIs, architectural shifts, and development enhancements introduced in WordPress 7.0.


--------------------------------------------------------------------------------


1. AI Infrastructure: The WP AI Client & Connectors API

WordPress 7.0 introduces a standardized, provider-agnostic layer for Generative AI. This architecture separates the "what" (the prompt and requirements) from the "how" (the specific provider and model routing).

1.1 WP AI Client (PHP API)

The wp_ai_client_prompt() function is the primary entry point, returning a WP_AI_Client_Prompt_Builder instance. This fluent builder allows for granular configuration of AI requests.

Feature Detection

Before invoking AI features, developers must use feature detection methods. These perform deterministic logic against available model capabilities without incurring API costs or latency.

Method	Description
is_supported_for_text_generation()	Checks if any active provider supports text output.
is_supported_for_image_generation()	Checks for image creation support.
is_supported_for_text_to_speech_conversion()	Checks for TTS capabilities.
is_supported_for_speech_generation()	Checks for native speech generation.
is_supported_for_video_generation()	Checks for video generation support.

Configuration Methods

Configuration	Method
Prompt text	with_text()
File input	with_file()
Conversation history	with_history()
System instruction	using_system_instruction()
Temperature	using_temperature()
Max tokens	using_max_tokens()
Top-p / Top-k	using_top_p(), using_top_k()
Stop sequences	using_stop_sequences()
Model preference	using_model_preference()
Output modalities	as_output_modalities()
Output file type	as_output_file_type()
JSON response	as_json_response()

Generation and Results

The API supports multiple modalities. For structured data, use as_json_response() with a JSON schema.

Text Generation Example:

$result = wp_ai_client_prompt( 'Summarize this post.' )
    ->as_json_response( [
        'type' => 'object',
        'properties' => [
            'summary' => [ 'type' => 'string' ],
            'reading_time' => [ 'type' => 'integer' ]
        ]
    ] )
    ->generate_text();


To retrieve usage data and provider information, use the specific result methods:

* generate_text_result()
* generate_image_result()
* convert_text_to_speech_result()
* generate_speech_result()
* generate_video_result()

These return a GenerativeAiResult object. Use getTokenUsage() to analyze token consumption (input, output, thinking) and getProviderMetadata() or getModelMetadata() for implementation-specific details.

1.2 Connectors API & Credential Management

The Connectors API handles external service metadata and authentication. AI providers registered via AiClient::defaultRegistry() are automatically discovered and integrated into the Settings > Connectors UI.

* Authentication Methods: Supports api_key and none.
* Credential Resolution Priority:
  1. Environment Variable: e.g., ANTHROPIC_API_KEY.
  2. PHP Constant: define( 'ANTHROPIC_API_KEY', '...' ).
  3. Database: Managed via the admin screen. Setting names follow the pattern connectors_{$type}_{$id}_api_key.

Registry Interaction

Within the wp_connectors_init hook, developers can modify the registry using the WP_Connector_Registry instance.

add_action( 'wp_connectors_init', function( $registry ) {
    if ( $registry->is_registered( 'openai' ) ) {
        $connector = $registry->unregister( 'openai' );
        $connector['description'] = 'Custom OpenAI description.';
        $registry->register( 'openai', $connector );
    }
} );


1.3 Client-Side Abilities API

The Abilities API provides a common interface for AI agents and plugins to interact with WordPress. It uses JSON Schema Draft-04 for input/output validation.

Script Modules

* @wordpress/abilities: Core state management for querying/executing abilities.
* @wordpress/core-abilities: Integration layer that fetches server-registered abilities via /wp-abilities/v1/.

REST API Mapping

For server-side abilities, the HTTP method is automatically determined by metadata annotations:

* readonly: true → GET
* destructive: true AND idempotent: true → DELETE
* All other cases → POST

JavaScript Implementation

import { registerAbility } from '@wordpress/abilities';

registerAbility({
    name: 'my-plugin/delete-log',
    label: 'Delete Log',
    category: 'maintenance',
    input_schema: { type: 'object', properties: { id: { type: 'number' } } },
    permissionCallback: () => currentUserCan( 'manage_options' ),
    callback: async ( { id } ) => { /* Implementation */ },
    meta: { 
        annotations: { 
            readonly: false, 
            destructive: true, 
            idempotent: true 
        } 
    }
});



--------------------------------------------------------------------------------


2. Block Development Enhancements

2.1 PHP-Only Block Registration

A new paradigm allows registering blocks entirely in PHP via the autoRegister flag in register_block_type().

* Requirements: A render_callback is mandatory.
* Attributes: Supports string, number, integer, and boolean.
* Constraint: Attributes must be stored in the block's JSON boundary; "sourced" attributes (e.g., from HTML) are not supported.

register_block_type( 'my-plugin/simple-notice', [
    'title'    => __( 'Simple Notice', 'my-plugin' ),
    'supports' => [ 'autoRegister' => true ],
    'attributes' => [
        'message' => [
            'type'  => 'string',
            'label' => __( 'Notice Text', 'my-plugin' ),
        ],
    ],
    'render_callback' => function( $attributes ) {
        return sprintf( '<div class="notice">%s</div>', esc_html( $attributes['message'] ) );
    },
] );


2.2 Iframed Editor Logic

WordPress 7.0 optimizes editor stability via conditional iframing.

* Dynamic Transition: The post editor is iframed by default if all inserted blocks use Block API v3+.
* Compatibility Fallback: If a Block API v2 (or lower) block is inserted, the editor dynamically switches out of the iframe mode to ensure the legacy block functions correctly.

2.3 Block Visibility (Viewport-Based)

7.0 introduces CSS-level hiding for viewports. Unlike blockVisibility: false (which prevents DOM rendering), viewport rules keep the block in the DOM but apply display: none via CSS.

* Naming Convention: The block support key is visibility, but the attribute metadata key containing the settings is blockVisibility.
* Metadata Structure:
* Server-Side Parsing: Developers parsing block markup must handle both scalar (boolean) and object (viewport rules) forms of the blockVisibility field.

2.4 Dimensions & Typography Supports

* Width/Height: Opt-in via supports.dimensions.width/height. Themes define presets in theme.json under settings.dimensions.dimensionSizes. The UI renders a slider for < 8 presets and a dropdown for 8+.
* Text Indent: The textIndent support adds a "Line Indent" control. For the core Paragraph block, the typography.textIndent setting applies at the Global Styles level:
  * subsequent (Default): Uses .wp-block-paragraph + .wp-block-paragraph.
  * all: Uses .wp-block-paragraph.


--------------------------------------------------------------------------------


3. Patterns and Navigation Architecture

3.1 Customisable Navigation Overlays

Theme developers can now design mobile overlays using blocks.

1. Register Area: In theme.json, add {"area": "navigation-overlay", "name": "mobile-menu"} to templateParts.
2. Implementation: Create the HTML file in /parts/. It is strongly recommended to include the core/navigation-overlay-close block explicitly to avoid design-clashing fallbacks.
3. Registration: Register as a pattern with blockTypes set to core/template-part/navigation-overlay.
4. Limitation: Custom overlays are currently tied to the active theme and are not preserved during theme switches. Use the slug-only (no theme prefix) in the Navigation block's overlay attribute for future-proofing.

3.2 Pattern Editing & contentOnly Mode

contentOnly is now the default mode for unsynced patterns.

* Opting Out: Use the disableContentOnlyForUnsyncedPatterns setting via the block_editor_settings_all filter.
* Requirements: Attributes intended for editing must have "role": "content" or the block must declare "contentRole": true in supports.

3.3 Pattern Overrides for Custom Blocks

Pattern Overrides are now powered by the Block Bindings API. Any block that supports Block Bindings can opt into Overrides via the block_bindings_supported_attributes filter.

* Technical Detail: For static blocks, WordPress uses the HTML API to locate attributes within persisted markup and replace them with bound values during rendering.


--------------------------------------------------------------------------------


4. DataViews and DataForms

4.1 DataViews API Updates

* groupBy: The legacy string-based property is replaced by a groupBy object supporting field, direction, and label.
* onReset: Controls the "Reset view" button.
  * undefined: Hides the button (no persistence).
  * false: Button is shown but disabled (persistence active, but at default state).
  * function: Button is active.

4.2 Field API & Validation

Declarative validation rules (JSON Schema-based) are expanded.

Rule	Supported Types
pattern	text, email, tel, url
minLength/maxLength	text, email, tel, url
min/max	integer, number

Use getValueFormatted to provide custom display logic, such as converting raw integers to human-readable file sizes (e.g., 2048 to "2.0 KB").


--------------------------------------------------------------------------------


5. Interactivity API & Core Utility Updates

5.1 Interactivity API: watch()

The watch() function allows side-effect subscriptions independent of the DOM. Combined with the now server-populated state.url, it enables reliable analytics tracking.

import { store, watch } from '@wordpress/interactivity';
const { state } = store( 'core/router' );

// Track virtual page views for analytics
watch( () => {
    sendAnalyticsPageView( state.url ); 
} );


5.2 Breadcrumb Block Filters

* block_core_breadcrumbs_items: Modifies the trail. Each item includes label, url, and allow_html.
* Security Note: If allow_html is true, the label is sanitized via wp_kses_post(). If false or omitted, it is escaped via esc_html().

5.3 Core Environment Changes

* PHP Minimum: 7.4.
* PHPMailer: 7.0.2.
* JS Linting: Transitioned to Espree for ES6 support.
* Security: Administrator and Editor roles are removed from the default user registration selector to prevent accidental privilege escalation. Use the default_role_dropdown_excluded_roles filter to modify this list.
