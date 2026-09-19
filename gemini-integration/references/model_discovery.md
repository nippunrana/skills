# Model Discovery

How to pick a Gemini or Gemma model without guessing.

## Table of Contents
1. [Why this exists](#why-this-exists)
2. [The protocol](#the-protocol)
3. [Trusted sources](#trusted-sources)
4. [Listing models from the SDK](#listing-models-from-the-sdk)
5. [Capability map (stable across releases)](#capability-map)
6. [Dated snapshot](#dated-snapshot)
7. [Preview, stable, and retirement](#preview-stable-and-retirement)
8. [Pinning the model in code](#pinning-the-model-in-code)

---

## Why this exists

Google ships Gemini releases every few months, renames tiers, and shuts down preview IDs with short notice. Any model ID written into a skill, a tutorial, or a code sample starts decaying the day it's written.

A stale ID fails at runtime with a 404 that reads like an auth or region problem, so it costs far more to debug than it did to avoid. Worse, a *plausible* invented ID (`gemini-4-ultra`, `gemma-4-e4b-it`) looks correct in review and only fails in production.

So: the capability *shape* of the lineup is durable enough to write down; the exact IDs are not. Look those up every time.

---

## The protocol

**1. Check live.** Before naming any model, fetch the official model list and/or call `models.list()` on the user's platform. Prefer `models.list()` when credentials exist — a web page tells you what Google has launched; `models.list()` tells you what *this* project, region, and account can actually call, which is the thing that matters.

**2. Report with provenance.** Tell the user where the information came from and that it was fetched just now:

> "From checking Google's official model list just now, the current Flash model is `X`, with `Y` as the previous generation and `Z` for high-throughput/low-cost work."

The point is that the user can distinguish a live fact from a recalled one, and knows the answer is as fresh as this session.

**3. Ask which model they want.** Offer 2–4 realistic candidates for *their* use case, not the full catalogue, each with a one-line trade-off:

> "For a support chatbot I'd suggest `X` (best balance of speed and reasoning). `Y` is cheaper and faster if replies are short and templated. `Z` reasons deepest but is slower and pricier. Which do you want?"

Recommend one. A user who doesn't know the lineup wants a default, not a menu.

**4. Pin it.** Put the confirmed ID in one place — an env var or a single exported constant — and reference that everywhere.

**5. If you can't check.** Say so explicitly. Then, in order: reuse the model ID already present in the user's codebase; or ask them to paste the current list from the AI Studio model picker or `gcloud`; or ask them to confirm an ID. Never fill the gap with a guess.

---

## Trusted sources

Use these, and treat them as authoritative in roughly this order:

| Source | URL | Good for |
|---|---|---|
| Gemini API model list | `ai.google.dev/gemini-api/docs/models` | Canonical IDs, tiers, preview/stable status, shutdown notices |
| Google Cloud model docs | `cloud.google.com` / `docs.cloud.google.com` (Vertex / Gemini Enterprise Agent Platform) | Vertex model availability, regions, enterprise features |
| Google Gen AI SDK repos | `github.com/googleapis/python-genai`, `github.com/googleapis/js-genai` | Current SDK surface, client flags, env vars |
| Google DeepMind | `deepmind.google/models` | Family capabilities and positioning |
| Google blogs | `blog.google`, `developers.googleblog.com` | Launch announcements and dates |

When the web tool supports domain filtering, restrict to these domains.

**Do not source model IDs, release dates, or capability claims from third-party aggregators, model-comparison sites, blog roundups, or forum posts.** They lag, and they reproduce each other's errors. They're acceptable only as a hint that something *may* have launched — then confirm on an official source before telling the user.

---

## Listing models from the SDK

The most reliable check, because it's scoped to the user's actual credentials.

**Python** (this is Google's own documented pattern for finding text-capable models):
```python
from google import genai

client = genai.Client()  # or the enterprise/Vertex client — see platforms.md

for m in client.models.list():
    if "generateContent" in (m.supported_actions or []):
        print(m.name, m.input_token_limit, m.output_token_limit)
```

**Node.js** — `ai.models.list()` returns a pager; check the installed SDK's reference for the exact iteration shape, then:
```javascript
import { GoogleGenAI } from "@google/genai";

const ai = new GoogleGenAI({});
for await (const m of await ai.models.list()) {
  console.log(m.name);
}
```

**REST (Developer API):**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models" \
  -H "x-goog-api-key: $GEMINI_API_KEY"
```

Notes:
- The returned list differs between the Developer API and Vertex, and on Vertex it varies by region — run it against the platform and location the app will actually use.
- Filter to models whose supported actions include `generateContent` before offering them for text/chat work; the list also contains embedding, image, and TTS models. (Over REST the same field is `supportedGenerationMethods`; the SDK exposes it as `supported_actions`.)
- Model names come back resource-qualified (`models/<id>`). Strip the prefix before passing the ID to `generate_content`.

---

## Capability map

Families and tiers change name less often than IDs do. Use this to reason about *which kind* of model fits, then resolve the current ID for that tier live.

| Tier / family | Optimized for | Reach for it when |
|---|---|---|
| **Flash** | Balanced speed, reasoning, and cost; the workhorse tier | Default for chat, agents, coding, extraction. Start here unless there's a reason not to |
| **Flash-Lite** | Lowest latency and cost per call | High volume, short or templated outputs, classification, routing |
| **Pro** | Deepest reasoning, hardest multi-step problems | Complex synthesis, long-document analysis, difficult reasoning; slower and pricier |
| **Live** | Real-time bidirectional voice/video sessions | Voice agents, live interpretation. Separate API surface from `generateContent` |
| **Image generation** | Creating and editing images from prompts | Image output, not image *understanding* (any multimodal model reads images) |
| **Speech (transcribe / TTS)** | Speech-to-text and text-to-speech | Transcription pipelines, spoken output |
| **Embedding** | Vector representations | Semantic search, RAG retrieval, clustering, classification |
| **Gemma (open weights)** | Portable, self-hostable, permissively licensed | Cost control or a future self-hosting path matters more than frontier capability. Narrower modality support than Gemini — verify before promising a modality. See [gemma_models.md](gemma_models.md) |
| **Specialized** | Computer use, deep research, robotics, music | Only when the task names that capability |

Durable rules of thumb:
- **Context windows are large but not uniform.** Check the specific model's limit rather than assuming the flagship number applies to the tier.
- **Higher tier ≠ better outcome.** Latency and cost are usually the binding constraints in a product; a Flash-class model with a good prompt beats a Pro-class model the user won't wait for.
- **Modality support is per-model, not per-family.** Confirm audio, video, PDF, and image-output support for the exact model before designing around it.
- **Thinking/reasoning effort is configurable** on thinking-enabled models and is billed as output tokens. Turn it down for simple tasks.

---

## Dated snapshot

**Captured 2026-09-19 from `ai.google.dev/gemini-api/docs/models`. Treat as a starting point for the live check, not as an answer — it is expected to be out of date.**

At that date the official list included, among others:

- Flash (stable): `gemini-3.8-flash` (newest, aimed at long-horizon agentic and engineering work), with `gemini-3.7-flash`, `gemini-3.6-flash`, `gemini-3.5-flash` as prior generations
- Flash-Lite (stable): `gemini-3.5-flash-lite`, `gemini-3.1-flash-lite`
- Pro: `gemini-3.1-pro-preview` (preview)
- Live: `gemini-3.8-live`, `gemini-3.8-live-extended-thinking`
- Image: `gemini-3.1-flash-image`, `gemini-3.1-flash-lite-image`, `gemini-3-pro-image`
- Speech: `gemini-3.5-transcribe`
- Embeddings: `gemini-embedding-2-preview`, `gemini-embedding-001`
- Already shut down at that date: `gemini-2.0-flash`, `gemini-2.0-flash-lite`, `gemini-3.1-flash-lite-preview`, `gemini-3-pro-preview`

That last line is the lesson: `gemini-3-pro-preview` was a current, recommended ID not long before it stopped answering.

---

## Preview, stable, and retirement

- IDs containing `-preview` or a date suffix (`-10-2025`, `-04-2026`) are temporary. They change behaviour and get withdrawn.
- Using one is fine for evaluation or for a capability that exists nowhere else — but tell the user it's preview and that it will need swapping.
- For production, prefer a stable ID and pin it exactly. Unversioned aliases silently move under the app.
- Check the models page for a deprecation/shutdown column when confirming a choice; Google publishes retirement dates there.

---

## Pinning the model in code

One definition, referenced everywhere, so a model swap is a one-line change:

**Python:**
```python
import os

MODEL_ID = os.environ.get("GEMINI_MODEL", "<confirmed-model-id>")
```

**Node.js:**
```javascript
export const MODEL_ID = process.env.GEMINI_MODEL ?? "<confirmed-model-id>";
```

Keep the literal fallback as the ID the user actually confirmed, and record next to it whether it was stable or preview at the time.
