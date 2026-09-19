# Client Setup & Authentication

## Table of Contents
1. [Before you start](#before-you-start)
2. [Python Setup](#python-setup)
3. [Node.js Setup](#nodejs-setup)
4. [REST / cURL Setup](#rest-setup)
5. [Credential Management](#credential-management)
6. [Model Selection](#model-selection)
7. [Thinking Configuration](#thinking-configuration)
8. [Generation Config Parameters](#generation-config-parameters)

---

## Before you start

Two things must be settled first, or the code below is guesswork:

- **Platform** — Google AI Studio (Developer API, API key) or Vertex AI / Gemini Enterprise Agent Platform (GCP project + ADC). See [platforms.md](platforms.md). The snippets below show the AI Studio client; swap in the enterprise client from `platforms.md` if that's the choice.
- **Model** — resolve it live and confirm it with the user. See [model_discovery.md](model_discovery.md).

`MODEL_ID` below is a placeholder, not a model name. Replace it with the confirmed ID, defined once (e.g. `MODEL_ID = os.environ.get("GEMINI_MODEL", "...")`) and referenced everywhere else.

---

## Python Setup

Install the Gen AI SDK:

```bash
pip install google-genai
```

Initialize the client:

```python
from google import genai

client = genai.Client(api_key="GEMINI_API_KEY")
```

Or use an environment variable (recommended):

```python
import os
from google import genai

client = genai.Client(api_key=os.environ["GEMINI_API_KEY"])
```

Quick test:

```python
response = client.models.generate_content(
    model=MODEL_ID,
    contents="How does AI work?"
)
print(response.text)
```

---

## Node.js Setup

Install the SDK:

```bash
npm install @google/genai
```

Initialize the client:

```javascript
import { GoogleGenAI } from "@google/genai";

const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
```

Quick test:

```javascript
async function main() {
  const response = await ai.models.generateContent({
    model: MODEL_ID,
    contents: "How does AI work?",
  });
  console.log(response.text);
}

await main();
```

---

## REST Setup

All Gemini API endpoints use this base URL:

```
https://generativelanguage.googleapis.com/v1beta/
```

Quick test:

```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/${MODEL_ID}:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{
    "contents": [{
      "parts": [{"text": "How does AI work?"}]
    }]
  }'
```

---

## Credential Management

### Getting an API Key (AI Studio)

1. Go to [Google AI Studio](https://aistudio.google.com/apikey)
2. Click "Create API key"
3. Copy the key and store it securely

### Security Best Practices

- **Never hardcode** API keys in source code
- **Never expose** API keys in client-side JavaScript (browser)
- Use **environment variables** for local development
- Use a **secrets manager** (e.g., AWS Secrets Manager, GCP Secret Manager, HashiCorp Vault) for production
- For browser-based apps, **proxy API calls through your backend** to keep the key server-side
- For mobile apps, use a backend proxy — never embed the key in the app bundle

### Environment Variable Setup

**bash / zsh:**
```bash
export GEMINI_API_KEY="your-api-key-here"
```

**.env file (with dotenv):**
```
GEMINI_API_KEY=your-api-key-here
```

If both `GOOGLE_API_KEY` and `GEMINI_API_KEY` are set, the SDK uses `GOOGLE_API_KEY`. Set one.

### Vertex AI credentials

No API key. The client authenticates with Application Default Credentials:

```bash
gcloud auth application-default login          # local development
export GOOGLE_CLOUD_PROJECT='your-project-id'
export GOOGLE_CLOUD_LOCATION='us-central1'
```

In production on Cloud Run / GKE / Compute Engine, attach a service account with the Vertex AI user role and skip key files entirely — workload identity is safer than a downloaded JSON key. Full client construction, env vars, and the `enterprise` vs `vertexai` flag caveat are in [platforms.md](platforms.md).

---

## Model Selection

Model IDs are deliberately not listed here — they change faster than this file can. Resolve the current lineup with the protocol in [model_discovery.md](model_discovery.md): check the official list or `client.models.list()`, report what was found, and confirm the choice with the user before writing it into code.

What to weigh when recommending one:

| Consideration | Ask |
|---|---|
| Latency budget | Is a user waiting on this response? Favour a Flash or Flash-Lite tier |
| Reasoning depth | Multi-step synthesis or hard problems? A Pro tier earns its cost |
| Volume | Thousands of short calls? Flash-Lite changes the bill materially |
| Modality | Audio, video, PDF, or image *output*? Verify per-model support — it is not uniform |
| Context size | Large but model-specific. Check the chosen model's limit rather than assuming |
| Licensing / portability | Need open weights or a self-hosting path? See [gemma_models.md](gemma_models.md) |
| Stability | Preview IDs get retired. Prefer stable for production and pin exactly |

---

### Thinking Configuration

Current Gemini models have native "thinking" enabled by default (confirm for the specific model — support and defaults vary by tier). Control it with the `thinking_config`:

**Python:**
```python
from google.genai import types

response = client.models.generate_content(
    model=MODEL_ID,
    contents="Solve this step by step: 15% of 340",
    config=types.GenerateContentConfig(
        thinking_config=types.ThinkingConfig(thinking_level="low")
    ),
)
```

**Node.js:**
```javascript
import { ThinkingLevel } from "@google/genai";

const response = await ai.models.generateContent({
  model: MODEL_ID,
  contents: "Solve this step by step: 15% of 340",
  config: {
    thinkingConfig: {
      thinkingLevel: ThinkingLevel.LOW,
    },
  },
});
```

Thinking levels: `"none"`, `"low"`, `"medium"`, `"high"`. Higher = more reasoning tokens (billed as output tokens). Use `"low"` for simple tasks, `"high"` for complex reasoning.

### Generation Config Parameters

Fine-tune generation behavior:

| Parameter | Description | Default |
|---|---|---|
| `temperature` | Randomness (0.0–2.0). Lower = more deterministic. | Model-dependent |
| `top_p` | Nucleus sampling threshold (0.0–1.0). | Model-dependent |
| `top_k` | Top-k sampling. Number of top tokens to consider. | Model-dependent |
| `max_output_tokens` | Maximum tokens in the response. | Model-dependent |
| `stop_sequences` | Strings that stop generation when encountered. | None |

**Python:**
```python
response = client.models.generate_content(
    model=MODEL_ID,
    contents="Explain quantum computing",
    config=types.GenerateContentConfig(
        temperature=0.1,
        max_output_tokens=500,
    ),
)
```

**Node.js:**
```javascript
const response = await ai.models.generateContent({
  model: MODEL_ID,
  contents: "Explain quantum computing",
  config: {
    temperature: 0.1,
    maxOutputTokens: 500,
  },
});
```

**REST:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/${MODEL_ID}:generateContent" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H 'Content-Type: application/json' \
  -X POST \
  -d '{
    "contents": [{"parts": [{"text": "Explain quantum computing"}]}],
    "generationConfig": {
      "temperature": 0.1,
      "maxOutputTokens": 500
    }
  }'
```
