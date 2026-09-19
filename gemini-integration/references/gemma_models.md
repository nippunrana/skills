# Gemma (Open-Weights) Models

## Table of Contents
1. [Overview](#overview)
2. [Gemma vs Gemini: When to Choose Which](#gemma-vs-gemini-when-to-choose-which)
3. [Available Models](#available-models)
4. [Quickstart](#quickstart)
5. [Recommended Sampling Parameters](#recommended-sampling-parameters)
6. [Thinking Mode](#thinking-mode)
7. [Function Calling](#function-calling)
8. [Structured Outputs](#structured-outputs)
9. [Google Search Grounding](#google-search-grounding)
10. [Multimodal Input](#multimodal-input)
11. [Gemma on the Interactions API](#gemma-on-the-interactions-api)

---

## Overview

Gemma is Google's open-weights model family (Apache 2.0 license). A subset of each Gemma generation is hosted by Google and callable through the same Gemini API surface — same SDK, same credentials, same `generateContent` call shape. Swapping the `model` string is usually enough to move an integration between Gemini and Gemma.

**Which Gemma models are API-callable changes with each release, and only some sizes are ever hosted** — the rest are weights-only downloads. Resolve the callable IDs live with the protocol in [model_discovery.md](model_discovery.md) before writing one into code; `client.models.list()` is the definitive answer for a given account. `GEMMA_MODEL_ID` in the samples below is a placeholder.

If the user is on Vertex AI rather than AI Studio, confirm how Gemma is served on that platform before designing around it — don't assume the call shape below transfers unchanged. See [platforms.md](platforms.md).

The rest of this skill's reference files (`content_generation.md`, `function_calling.md`, `structured_outputs.md`, `built_in_tools.md`) apply to Gemma the same way they apply to Gemini, since the API contract is shared. This file covers what's specific to Gemma: which models exist, what they don't support, and Gemma-specific configuration.

---

## Gemma vs Gemini: When to Choose Which

| Choose Gemma when… | Choose Gemini when… |
|---|---|
| An open-weights model (Apache 2.0) the user could later self-host matters | Audio input/output or image generation is needed |
| Cost efficiency matters more than absolute frontier capability | The largest context windows are needed — Gemma's are substantially smaller |
| Gemma's smaller context window is sufficient for the workload | The lowest-latency/highest-throughput tier is the priority |
| Portability across the API and self-hosted deployments matters | No plans to ever self-host |

Raise these trade-offs during model discovery (Phase 1) if the user mentions Gemma, "open model," "open weights," or cost-sensitive deployment — don't assume Gemma is a drop-in Gemini replacement without checking they don't need audio or the larger context window.

---

## Available Models

**Only a few Gemma sizes are served on the API at any time.** The remaining sizes in each generation are weights-only downloads and are **not** callable model IDs on `generativelanguage.googleapis.com`.

This is the single most common Gemma mistake: the family is published openly with many size variants, so a plausible-looking ID is easy to assemble from a model card and then fails at call time. Never construct a Gemma model ID from a size seen on a weights page — take it from `client.models.list()` or the official model list ([model_discovery.md](model_discovery.md)).

What to confirm about the specific Gemma model before designing around it:

| Property | Why it matters |
|---|---|
| Callable on the API at all | Most family members are download-only |
| Architecture (dense vs MoE) | MoE variants trade some quality for throughput at similar cost |
| Context window | Considerably smaller than the Gemini flagship tiers |
| Image / video input | Generally supported on the hosted instruction-tuned variants |
| Audio input | **Historically not supported on the API-hosted Gemma models.** If the task involves speech-to-text, voice input, or audio analysis, use a Gemini model — and verify rather than assume this has changed |
| Image output | Gemini-only; Gemma reads images, it doesn't generate them |

Hosted Gemma models are instruction-tuned ("it") chat models.

---

## Quickstart

Gemma models use the exact same client and call shape as Gemini — only the `model` string changes.

**Python:**
```python
from google import genai

client = genai.Client(api_key=os.environ["GEMINI_API_KEY"])

response = client.models.generate_content(
    model=GEMMA_MODEL_ID,
    contents="Explain the difference between a dense and MoE architecture in two sentences.",
)
print(response.text)
```

**Node.js:**
```javascript
import { GoogleGenAI } from "@google/genai";

const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });

const response = await ai.models.generateContent({
  model: GEMMA_MODEL_ID,
  contents: "Explain the difference between a dense and MoE architecture in two sentences.",
});
console.log(response.text);
```

**REST:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/${GEMMA_MODEL_ID}:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{
    "contents": [{"parts": [{"text": "Explain the difference between a dense and MoE architecture in two sentences."}]}]
  }'
```

Which hosted size to default to: the largest dense variant for reasoning-heavy or coding tasks where quality matters most; a mixture-of-experts variant when throughput and latency matter more and near-equivalent quality at lower active-parameter cost is an acceptable trade.

---

## Recommended Sampling Parameters

Gemma's documented sampling defaults differ from Gemini's — set these explicitly rather than relying on API defaults:

```python
config=types.GenerateContentConfig(
    temperature=1.0,
    top_p=0.95,
    top_k=64,
)
```

---

## Thinking Mode

Gemma supports the same `thinking_config` mechanism as Gemini's thinking models — configure it through the API rather than raw prompt tokens.

**Python:**
```python
response = client.models.generate_content(
    model=GEMMA_MODEL_ID,
    contents="A train leaves station A at 60mph, another leaves station B (300 miles away) at 40mph toward A. When do they meet?",
    config=types.GenerateContentConfig(
        thinking_config=types.ThinkingConfig(thinking_level="high"),
    ),
)
```

Reserve `thinking_level="high"` for logic-heavy tasks — algorithmic problems, multi-step math, debugging — where extra reasoning tokens improve accuracy. For simple lookups or formatting tasks, leave thinking off (or low) to save latency and cost, same guidance as for Gemini in `client_setup.md`.

---

## Function Calling

Gemma supports native function calling with the same `types.Tool` / function-declaration schema used for Gemini — see `function_calling.md` for the full agentic tool-loop pattern (define schema → model returns `functionCall` → your code executes → return `functionResponse`). No Gemma-specific changes are needed to that pattern.

```python
get_order_status = {
    "name": "get_order_status",
    "description": "Look up the current status of a customer order.",
    "parameters": {
        "type": "object",
        "properties": {
            "order_id": {"type": "string", "description": "The order ID, e.g. ORD-1234"},
        },
        "required": ["order_id"],
    },
}

response = client.models.generate_content(
    model=GEMMA_MODEL_ID,
    contents="What's the status of order ORD-1234?",
    config=types.GenerateContentConfig(tools=[types.Tool(function_declarations=[get_order_status])]),
)

if response.function_calls:
    for fc in response.function_calls:
        print(f"Call: {fc.name}({fc.args})")
```

---

## Structured Outputs

`response_mime_type="application/json"` with a `response_schema` (see `structured_outputs.md`) is a `generateContent`-level configuration, not a Gemini-specific one, so it's expected to work with Gemma requests the same way. This isn't called out explicitly in Gemma's own documentation, though — if a task depends on strict schema conformance, test the schema against the target Gemma model before shipping it, and have a prompted-JSON-plus-parsing fallback ready in case conformance is weaker than on Gemini.

---

## Google Search Grounding

Gemma supports the `google_search` built-in tool the same way Gemini does — see `built_in_tools.md` for the full pattern, including reading `grounding_metadata` for citations.

```python
response = client.models.generate_content(
    model=GEMMA_MODEL_ID,
    contents="What are the dates for cherry blossom season in Tokyo this year?",
    config=types.GenerateContentConfig(tools=[{"google_search": {}}]),
)

for chunk in response.candidates[0].grounding_metadata.grounding_chunks:
    if chunk.web:
        print(f"Source: {chunk.web.title} — {chunk.web.uri}")
```

---

## Multimodal Input

Both API-hosted Gemma models accept image and video input (no audio — see [Available Models](#available-models)). Follow the same File API / inline-data patterns as `content_generation.md`, with two Gemma-specific notes:

- **Modality ordering**: place image content before the text prompt in the `contents` list for best results.
- **Video**: processed as roughly one frame per second; keep clips short and favor a lower visual token budget per frame to control token cost.
- **Visual token budget**: Gemma supports a configurable per-image token budget (documented values: 70, 140, 280, 560, 1120) to trade detail against cost — low budgets for classification or video frames, high budgets for OCR or dense document parsing. Confirm the exact config field name in the SDK version you're using before relying on it, as it isn't part of the base `GenerateContentConfig` surface documented for Gemini.

---

## Gemma on the Interactions API

The API-hosted Gemma models are listed as supported on the Interactions API — Google's newer, stateful alternative to `generateContent` (typed `steps` instead of role-based `contents`, optional `background=True` for long-running async execution, session retention up to 55 days on paid tiers / 1 day on free). This skill's other reference files are written against `generateContent`; reach for the Interactions API with a Gemma model when the task specifically needs server-side state or backgrounded execution rather than by default.

```python
response = client.interactions.create(
    model=GEMMA_MODEL_ID,
    background=True,
    input="Analyze this 200-page report and summarize the key risks.",
)
print(f"Interaction ID: {response.id}")
```
