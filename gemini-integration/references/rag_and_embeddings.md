# RAG & Embeddings

> Code samples use `MODEL_ID` (and `IMAGE_MODEL_ID` / `EMBEDDING_MODEL_ID` / `GEMMA_MODEL_ID`) as placeholders.
> Substitute the model ID confirmed with the user during discovery — see [model_discovery.md](model_discovery.md).
> Client construction differs between AI Studio and Vertex AI, and the REST samples here target the AI Studio
> endpoint — Vertex uses a regional host, a project/location path, and a bearer token. See [platforms.md](platforms.md).

## Table of Contents
1. [File Search (Managed RAG)](#file-search-managed-rag)
2. [Embeddings API](#embeddings-api)
3. [Task Types for Embeddings](#task-types-for-embeddings)
4. [Embedding Aggregation Strategies](#embedding-aggregation-strategies)
5. [Choosing a RAG Strategy](#choosing-a-rag-strategy)

---

## File Search (Managed RAG)

File Search is Google's managed RAG solution. You upload documents, and the system handles chunking, embedding, storage, and retrieval automatically.

Key facts:
- File API uploads expire in **48 hours**, but FileSearchStore embeddings **persist indefinitely**.
- Uses a multimodal embedding model (text + images in documents).

### Creating a Store and Uploading Documents

**Python:**
```python
# Create a FileSearchStore
store = client.file_search.create_store(
    display_name="Engineering Docs",
    embedding_model="models/EMBEDDING_MODEL_ID"  # Required for multimodal
)

# Upload documents to the store
client.file_search.upload(store_name=store.name, file_path="manual.pdf")
client.file_search.upload(store_name=store.name, file_path="spec.pdf")
```

**Node.js:**
```javascript
const store = await client.fileSearch.createStore({
  displayName: "Engineering Docs",
  embeddingModel: "models/EMBEDDING_MODEL_ID",
});

await client.fileSearch.upload({
  storeName: store.name,
  filePath: "manual.pdf",
});
```

**REST:**
```bash
curl -X POST "https://generativelanguage.googleapis.com/v1beta/fileSearchStores" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"display_name": "Engineering Docs", "embedding_model": "models/EMBEDDING_MODEL_ID"}'
```

### Querying a Store

Once documents are uploaded, use the File Search tool in your generation requests:

```python
response = client.models.generate_content(
    model=MODEL_ID,
    contents="What are the safety requirements in section 4.2?",
    config={
        "tools": [{
            "file_search": {
                "store_names": [store.name]
            }
        }]
    }
)
```

---

## Embeddings API

Use embeddings for semantic search, classification, clustering, and building custom RAG pipelines.

### Generate Embeddings

**Python:**
```python
result = client.models.embed_content(
    model=EMBEDDING_MODEL_ID,
    contents="What is the meaning of life?"
)
print(result.embeddings)
```

**Node.js:**
```javascript
const response = await ai.models.embedContent({
  model: EMBEDDING_MODEL_ID,
  contents: "What is the meaning of life?",
});
console.log(response.embeddings);
```

**REST:**
```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/${EMBEDDING_MODEL_ID}:embedContent" \
  -H "Content-Type: application/json" \
  -H "x-goog-api-key: $GEMINI_API_KEY" \
  -d '{
    "model": "models/EMBEDDING_MODEL_ID",
    "content": {"parts": [{"text": "What is the meaning of life?"}]}
  }'
```

### Embedding Models

Google ships two kinds of embedding model, and the choice is about modality rather than quality:

| Kind | Modalities | Best For |
|---|---|---|
| Multimodal embedding | Text, image, video, audio, docs | Multimodal search, cross-modal retrieval |
| Text embedding | Text only | Text-only search, classification, clustering |

Resolve the current IDs and output dimensions from the model list ([model_discovery.md](model_discovery.md)). Embeddings from different models are not comparable — re-embed the whole corpus when switching, or retrieval silently degrades.

### Batch Embeddings

Embed multiple texts in one call:

```python
result = client.models.embed_content(
    model=EMBEDDING_MODEL_ID,
    contents=[
        "What is machine learning?",
        "How do neural networks work?",
        "What is deep learning?",
    ]
)
# result.embeddings contains one embedding per input
```

---

## Task Types for Embeddings

Specifying the right task type optimizes embeddings for the intended use case.

### Multimodal embedding models (prompt-based)

Format your input with a task prefix:

**Asymmetric retrieval (query ≠ document):**
```python
# For the query
query = "task: search result | query: How does photosynthesis work?"

# For the document being searched
document = "title: Biology 101 | text: Photosynthesis is the process by which plants convert sunlight..."
```

**Symmetric tasks (query = document format):**
```python
# Classification
text = "task: classification | query: The stock market rose sharply today."

# Clustering
text = "task: clustering | query: Recent advances in renewable energy."

# Semantic similarity
text = "task: sentence similarity | query: The cat sat on the mat."
```

### Text embedding models (config-based)

Use the `task_type` parameter:

```python
from google.genai import types

result = client.models.embed_content(
    model=EMBEDDING_MODEL_ID,
    contents=["What is AI?", "How does ML work?"],
    config=types.EmbedContentConfig(task_type="SEMANTIC_SIMILARITY")
)
```

Supported task types: `RETRIEVAL_QUERY`, `RETRIEVAL_DOCUMENT`, `SEMANTIC_SIMILARITY`, `CLASSIFICATION`, `CLUSTERING`, `QUESTION_ANSWERING`, `FACT_VERIFICATION`.

---

## Embedding Aggregation Strategies

When providing multiple inputs:

- **Aggregated Embedding**: Multiple inputs in the `contents` parameter create a single post-level representation. Use for comparing composite content.
- **Separate Embeddings**: Wrapping inputs in individual `Content` objects generates separate retrieval points. Use when each piece should be independently searchable.

---

## Choosing a RAG Strategy

| Approach | Setup Complexity | Best For | Limitations |
|---|---|---|---|
| **Long Context** (1M tokens) | None — just include text | Single document analysis, one-off queries | No persistence, cost scales with context size |
| **File Search** (managed) | Low — upload and query | Multi-document knowledge bases, production apps | Less control over retrieval logic |
| **Custom Vector DB** | High — build your own pipeline | Full control, hybrid search, metadata filtering | Requires infra (Pinecone, Weaviate, pgvector, etc.) |

**Recommendation**: Start with Long Context for prototyping. Move to File Search for production knowledge bases. Use a custom vector DB only when you need advanced retrieval features (hybrid search, metadata filtering, re-ranking).
