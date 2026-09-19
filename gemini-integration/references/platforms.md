# Platforms: Google AI Studio vs Vertex AI

## Table of Contents
1. [The two options](#the-two-options)
2. [Naming](#naming)
3. [Verified differences](#verified-differences)
4. [How to choose](#how-to-choose)
5. [Client construction](#client-construction)
6. [What stays the same](#what-stays-the-same)
7. [Moving from AI Studio to Vertex](#moving-from-ai-studio-to-vertex)
8. [Common failures](#common-failures)

---

## The two options

Gemini models are served through two Google products. Same models, same SDK, same request shape — different front door, different auth, different billing.

| | **Google AI Studio** — Gemini Developer API | **Vertex AI** — Gemini Enterprise Agent Platform |
|---|---|---|
| Credential | API key | Google Cloud service account / Application Default Credentials |
| Where you get it | [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | GCP console: enable the API, set up IAM and billing |
| Scoping | Key | GCP project + location (region, or `global`) |
| Billing | Google AI billing; a free tier is available | GCP billing account |
| Positioning (Google's own) | The fastest path to build and scale; recommended for most developers | Enterprise-ready controls and ecosystem |

---

## Naming

Google renamed **Vertex AI** to the **Gemini Enterprise Agent Platform** in 2026. In practice both names are in active use: existing code, `gcloud` surfaces, older documentation pages, and most users still say "Vertex".

Treat them as the same platform. Mirror whichever name the user used instead of correcting them — and when reading Google's docs, expect to hit both names in the same session.

---

## Verified differences

Only these are worth encoding; everything else changes too fast to pin here. Confirm current specifics on `cloud.google.com` and `ai.google.dev` when a decision hinges on one.

- **Authentication.** Developer API uses API keys. The enterprise platform requires Google Cloud service accounts / ADC — there is no API-key-only path with the same guarantees.
- **Project and region.** The enterprise platform requires a GCP project ID and a location. Model availability and quota vary by region, and the `global` endpoint has its own quota pool and does **not** satisfy data-residency requirements.
- **Data residency.** The enterprise platform offers data residency, access transparency, and enterprise-grade security controls. The Developer API does not make residency guarantees.
- **Provisioned throughput.** Reserved capacity is an enterprise-platform feature; multi-region endpoints allow one commitment to cover a jurisdiction rather than per-region allocations.
- **MLOps ecosystem.** Tuning, pipelines, feature stores, RAG engine, model garden, monitoring, and deployment tooling live on the enterprise platform.

---

## How to choose

Ask the user — but do the legwork first so the question is informed rather than cold.

**Evidence to gather from the project before asking:**
- `GEMINI_API_KEY` / `GOOGLE_API_KEY` in `.env` or config → already on AI Studio
- `GOOGLE_CLOUD_PROJECT`, `GOOGLE_APPLICATION_CREDENTIALS`, a service-account JSON, `gcloud` config, or a `@google-cloud/*` dependency → already on GCP
- Deployment target: Cloud Run / GKE / App Engine makes Vertex nearly free to adopt (ADC is already present); Vercel / Netlify / a VPS makes an API key much simpler

**Point to Vertex when** the app needs IAM-governed access, VPC-SC, audit logging, data residency, reserved throughput, or it already lives in GCP.

**Point to AI Studio when** it's a prototype, a side project, a startup shipping fast, or any app with no compliance constraint — and mention that the migration path later is a client-construction change, not a rewrite.

---

## Client construction

This is the only part of the integration that differs.

> **Verify the flag against the installed SDK version.** The SDK renamed this option: current `google-genai` / `@google/genai` releases use `enterprise` / `GOOGLE_GENAI_USE_ENTERPRISE`, while older releases and older documentation pages use `vertexai` / `GOOGLE_GENAI_USE_VERTEXAI`. Check the README of the version in the project's lockfile and use one spelling consistently — mixing them is a confusing failure.

### Python

```python
from google import genai

# Google AI Studio (Gemini Developer API)
client = genai.Client(api_key=os.environ["GEMINI_API_KEY"])

# Vertex AI / Gemini Enterprise Agent Platform
client = genai.Client(
    enterprise=True,              # older SDKs: vertexai=True
    project=os.environ["GOOGLE_CLOUD_PROJECT"],
    location=os.environ.get("GOOGLE_CLOUD_LOCATION", "us-central1"),
)
```

Environment-driven, so one build runs on either platform:

```bash
# AI Studio
export GEMINI_API_KEY='...'

# Vertex
export GOOGLE_GENAI_USE_ENTERPRISE=true     # older SDKs: GOOGLE_GENAI_USE_VERTEXAI
export GOOGLE_CLOUD_PROJECT='your-project-id'
export GOOGLE_CLOUD_LOCATION='us-central1'
```

```python
client = genai.Client()   # reads the environment
```

### Node.js

```javascript
import { GoogleGenAI } from "@google/genai";

// Google AI Studio
const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });

// Vertex AI / Gemini Enterprise Agent Platform
const ai = new GoogleGenAI({
  enterprise: true,             // older SDKs: vertexai: true
  project: process.env.GOOGLE_CLOUD_PROJECT,
  location: process.env.GOOGLE_CLOUD_LOCATION ?? "us-central1",
});

// Or configured entirely from the environment
const ai = new GoogleGenAI({});
```

Vertex credentials come from Application Default Credentials — `gcloud auth application-default login` locally, or the attached service account on Cloud Run / GKE / Compute Engine. Prefer workload identity over a downloaded key file.

### REST

The endpoints differ:

```bash
# AI Studio — API key in a header
curl "https://generativelanguage.googleapis.com/v1beta/models/${MODEL_ID}:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"contents":[{"parts":[{"text":"How does AI work?"}]}]}'

# Vertex — regional host, project/location in the path, OAuth bearer token
curl "https://${LOCATION}-aiplatform.googleapis.com/v1/projects/${PROJECT_ID}/locations/${LOCATION}/publishers/google/models/${MODEL_ID}:generateContent" \
  -H "Authorization: Bearer $(gcloud auth print-access-token)" \
  -H 'Content-Type: application/json' \
  -d '{"contents":[{"parts":[{"text":"How does AI work?"}]}]}'
```

Confirm the current host, API version, and path shape in the docs before shipping REST calls — the SDKs absorb these changes, hand-rolled URLs don't.

---

## What stays the same

Once the client exists, everything else in this skill is platform-independent: `generateContent`, streaming, chat, system instructions, structured outputs, function calling, token counting, and safety settings all take the same shape.

Differences that do surface:
- **Model availability**: not every model, especially previews, reaches every Vertex region at launch. Resolve IDs against the chosen platform — see [model_discovery.md](model_discovery.md).
- **Some features are platform-specific** (File Search, tuning, RAG engine, batch). Verify support before designing around one.

---

## Moving from AI Studio to Vertex

The usual path: prototype on an API key, move when compliance or scale demands it.

1. Enable the API and billing on the GCP project; grant the service account the Vertex AI user role.
2. Swap client construction (above) — call sites stay unchanged.
3. Re-resolve the model ID for the target region; confirm availability there.
4. Replace key-based secrets with ADC / workload identity and remove the API key from the deployment.
5. Re-check quotas — they're per-project-per-region and unrelated to the previous key's limits.

Keeping the client construction in one module from day one makes this a single-file change.

---

## Common failures

- **Both `GOOGLE_API_KEY` and `GEMINI_API_KEY` set** — the SDK prefers `GOOGLE_API_KEY`, so calls bill to a key the developer didn't intend.
- **`enterprise` / `vertexai` flag mismatched to the SDK version** — reads as "unexpected keyword argument" or a silently ignored option that sends traffic to the wrong backend.
- **API key passed to a Vertex client** — Vertex expects ADC; the key is ignored and auth fails confusingly.
- **Model 404 on Vertex** — usually the model isn't served in that region, not a bad ID. Re-run the model list for that location.
- **ADC missing locally** — works in CI or on Cloud Run, fails on the developer's laptop. `gcloud auth application-default login`.
- **Quota surprises after migrating** — Vertex quotas are per project and region, and the `global` endpoint pools separately.
