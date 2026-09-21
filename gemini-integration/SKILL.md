---
name: gemini-integration
description: Integrate Google Gemini AI and Gemma models into web and mobile apps. Use for Google AI Studio or Vertex AI SDK setup, multimodal generation, streaming, structured JSON outputs, tool/function calling, and live model discovery.
---

# Gemini AI Integration

A skill for integrating Google Gemini into websites, web apps, and mobile apps — covering platform choice, live model selection, client setup, content generation, streaming, structured outputs, function calling, built-in tools, RAG, embeddings, and production hardening.

**Two decisions come before any code**: which *platform* (Phase 0) and which *model* (Phase 1). Both change over time, so this skill resolves them live rather than from memory.

---

## Reference Directory

Read these on demand based on the task at hand:

| Reference File | When to Read |
|---|---|
| [model_discovery.md](references/model_discovery.md) | **Any time a model ID is needed.** The live-check protocol, trusted sources, `models.list()`, current family snapshot |
| [platforms.md](references/platforms.md) | Choosing AI Studio vs Vertex AI, auth differences, migrating between them |
| [client_setup.md](references/client_setup.md) | SDK install and client init for both platforms, API key management, generation config |
| [content_generation.md](references/content_generation.md) | Text generation, multimodal inputs, streaming, multi-turn chat, system instructions |
| [structured_outputs.md](references/structured_outputs.md) | JSON schema enforcement, type-safe responses, data extraction |
| [function_calling.md](references/function_calling.md) | Connecting Gemini to external APIs, agentic tool loops, thought signatures |
| [built_in_tools.md](references/built_in_tools.md) | Google Search grounding, Maps, code execution, URL context |
| [rag_and_embeddings.md](references/rag_and_embeddings.md) | File Search, vector embeddings, custom RAG pipelines, semantic search |
| [production_optimization.md](references/production_optimization.md) | Caching, inference tiers, safety settings, token management, cost, error handling |
| [gemma_models.md](references/gemma_models.md) | Gemma open-weights models via the same API, Gemma vs Gemini trade-offs |

**Code samples in the references use `MODEL_ID` as a placeholder.** Substitute the model the user confirmed in Phase 1 — never paste a literal ID from a sample.

---

## Core Workflow

Phase 0 (platform) → Phase 1 (model) → Phase 2 (client) → Phase 3 (feature) → Phase 4 (production).

---

## Phase 0 — Choose the Platform

Google serves the same Gemini models two ways. Picking wrong means rewriting auth, deployment, and billing later, so settle it before writing code.

| | **Google AI Studio** (Gemini Developer API) | **Vertex AI** (Gemini Enterprise Agent Platform) |
|---|---|---|
| Auth | API key | Google Cloud service account / ADC |
| Setup | Key from [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | GCP project + region, IAM, billing enabled |
| Billing | Google AI billing, free tier available | GCP billing account |
| Best for | Prototypes, indie and startup apps, fastest path to first call | Enterprise controls: IAM, VPC, audit logs, data residency, provisioned throughput, MLOps tooling |

Google's own guidance: most developers should start on the Developer API and move to the enterprise platform when they need those specific controls.

**Naming trap**: Google renamed Vertex AI to *Gemini Enterprise Agent Platform* in 2026. Users, older tutorials, and existing code all still say "Vertex". Treat the names as the same thing and use whichever the user used.

**How to decide**: ask the user, but bring evidence first. Check the project for `gcloud` config, a `GOOGLE_CLOUD_PROJECT` env var, service-account JSON, or an existing `GEMINI_API_KEY` — if the project already leans one way, say so and confirm rather than asking cold. If the app has enterprise requirements (data residency, VPC-SC, audit logging), surface that Vertex is the one that satisfies them.

Both platforms use the **same SDK** (`google-genai` / `@google/genai`) and the same `generateContent` call shape — only client construction differs. See [platforms.md](references/platforms.md) and [client_setup.md](references/client_setup.md).

---

## Phase 1 — Discover and Confirm the Model (do not skip)

Google ships new Gemini models every few months and retires preview IDs on short notice. A model ID recalled from memory is the single most likely thing in a Gemini integration to be stale, wrong, or already shut down — which fails at runtime with a confusing 404, not at build time.

**Never write a model ID from memory, and never carry one over from a code sample or an old file without checking it.**

Instead, before proposing any model:

1. **Check live.** Fetch the official model list (see [model_discovery.md](references/model_discovery.md) for trusted sources) and/or call `client.models.list()` against the user's chosen platform. `models.list()` is the stronger signal — it reflects what that account, project, and region can actually call.
2. **Report the finding with its provenance**, in the user's own terms — e.g. *"From checking Google's official model list just now, the current models are …"* — so they can tell a live fact from a remembered one.
3. **Ask which model they want**, with a recommendation for their use case and the trade-off in one line (speed vs depth vs cost). Present 2–4 realistic candidates, not the whole catalogue.
4. **Pin the exact ID** they choose everywhere in the generated code, ideally behind one env var or constant so it can be swapped without touching call sites.

**If web access and `models.list()` are both unavailable**: say so plainly, fall back to whatever model ID already exists in the user's codebase, and ask them to confirm. Inventing a plausible-looking ID is the worst outcome — it looks right and fails later.

**Preview vs stable**: preview IDs (`…-preview`, dated suffixes) get retired. Don't ship one to production without telling the user it's temporary.

Use [model_discovery.md](references/model_discovery.md) for the capability map (which family suits which job), the trusted-source list, and a dated snapshot of what was current when this skill was last updated.

---

## Phase 2 — Client Setup

1. Initialize the client for the chosen platform ([client_setup.md](references/client_setup.md)).
2. Keep credentials server-side. API keys and service-account JSON never ship to a browser or mobile bundle — proxy through a backend.
3. Make a single smoke call with the confirmed model before building anything on top of it. This catches a wrong model ID, region, or permission immediately rather than three files later.

---

## Phase 3 — Feature Implementation

| Building This? | Read This |
|---|---|
| Text generation or chat | [content_generation.md](references/content_generation.md) |
| Image/video/audio/PDF analysis | [content_generation.md](references/content_generation.md) |
| Generating images from a text prompt | [content_generation.md](references/content_generation.md) |
| Streaming responses for real-time UI | [content_generation.md](references/content_generation.md) |
| Extracting structured data (JSON) | [structured_outputs.md](references/structured_outputs.md) |
| Calling external APIs from Gemini | [function_calling.md](references/function_calling.md) |
| Search-grounded or location-aware answers | [built_in_tools.md](references/built_in_tools.md) |
| Document search / knowledge base | [rag_and_embeddings.md](references/rag_and_embeddings.md) |
| Semantic search / classification | [rag_and_embeddings.md](references/rag_and_embeddings.md) |
| Open-weights models | [gemma_models.md](references/gemma_models.md) |

Not every feature exists on both platforms or on every model — Live/audio, image generation, File Search, and computer use are model-specific. Confirm support for the chosen model during discovery rather than assuming.

---

## Phase 4 — Production Hardening

1. **Safety settings**: configure filtering thresholds appropriate to the app.
2. **Context caching**: cache repeated large inputs to cut cost and latency.
3. **Inference tier**: Priority (reliable), Standard (balanced), Flex (cheap), Batch (async bulk).
4. **Token management**: `countTokens` for pre-flight validation; watch thinking-token counts for cost.
5. **Error handling**: retries with exponential backoff for transient failures; handle 404 on a retired model ID explicitly.
6. **Model pinning**: pin the exact ID in config and note its status (stable vs preview), so a future upgrade is a deliberate one-line change.

See [production_optimization.md](references/production_optimization.md).

---

## Key Design Patterns

### Streaming Chat
Use `generateContentStream` to display tokens as they arrive — critical for perceived performance in chat UIs. See [content_generation.md](references/content_generation.md).

### Multimodal Upload
- **Small files** (<20MB): inline base64.
- **Large files**: upload via the File API, then reference by URI (uploads expire — check the current TTL in the docs).
- Use `media_resolution` to trade quality against token cost.

### Agentic Tool Loop
Define function schemas → model returns a `functionCall` → your code executes it → return a `functionResponse`. Thought signatures from thinking-enabled models must be returned exactly as received. See [function_calling.md](references/function_calling.md).

### Hybrid RAG
- **Long context**: simplest, best for single-document analysis, no infra.
- **File Search** (managed RAG): best for multi-document knowledge bases.
- **Custom vector DB**: when you need full control over retrieval and ranking.

See [rag_and_embeddings.md](references/rag_and_embeddings.md).

---

## Security

- Never expose an API key in frontend JavaScript or a mobile bundle — always proxy through a backend.
- Use environment variables locally and a secrets manager in production. On Vertex, prefer workload identity / ADC over a downloaded service-account key file.
- If both `GOOGLE_API_KEY` and `GEMINI_API_KEY` are set, the SDK uses `GOOGLE_API_KEY` — a real source of "why is it billing the wrong project" confusion.
- Configure safety settings to match the use case.

---

## Harness Note

This skill is agent-neutral. Where it says "ask the user", use whatever question mechanism the running agent has (an interactive question tool, or simply asking in the reply and waiting). Where it says "check live", use the available web search/fetch tool, or an SDK call, or ask the user to paste the current model list. Don't skip a step because a specific named tool is missing — do it with what's available, and say which route was used.
