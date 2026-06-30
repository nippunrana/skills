---
name: retrieval-and-ranking
description: >
  Use for any Python retrieval or ranking task: semantic search, vector search,
  embedding search, sentence-transformers, SentenceTransformer, all-MiniLM-L6-v2,
  cosine similarity, dot-product similarity, bi-encoder, encode documents, encode
  query, BM25, lexical search, keyword search, full-text search, rank_bm25,
  BM25Okapi, hybrid search, Reciprocal Rank Fusion, RRF, NDCG, MAP, P@k, MRR,
  Recall@k, retrieval evaluation metrics, ranking metrics, mean average precision,
  normalized discounted cumulative gain, retrieval pipeline, CPU-friendly embeddings,
  local embeddings without API, static embeddings, model2vec, offline retrieval.
  Invoke when the user wants to: add or improve a retrieval function, build a
  semantic search script, implement BM25 lexical retrieval, combine BM25 and
  semantic search, validate retrieval quality with NDCG/MAP/P@k, measure ranking
  performance, build a local embedding pipeline (no OpenAI/Cohere API), or compute
  retrieval metrics to benchmark a search system.
---

# Retrieval & Ranking Engineer
*Target: CPU-friendly Python retrieval — semantic search, BM25, hybrid fusion, local eval
metrics. No paid inference API required; models download once from HuggingFace, then run
fully offline.*

> **Sibling skill:** all code-quality rules, the ruff → mypy → pytest verification loop,
> and surgical-edit rules are in the `python-engineering` skill. Defer to it for those
> concerns; this skill owns the retrieval and ranking domain.

---

## How to Approach a Request

Route to the matching section before writing a single line:

| Request type | Load first | Then… |
|---|---|---|
| Semantic / vector / embedding search | `references/semantic-search.md` | Pick model, encode, call `util.semantic_search` |
| BM25 / keyword / lexical retrieval | `references/bm25.md` | Preprocess, init `BM25Okapi`, call `get_scores` |
| Hybrid BM25 + semantic | both above + `references/hybrid-fusion.md` | Run both, fuse with RRF |
| NDCG / MAP / P@k / MRR evaluation | `references/evaluation.md` | Use `scripts/metrics.py`; see conventions |
| Scaling: FAISS / vector DB / reranking | `references/scaling.md` | Only load when explicitly requested |

**Workflow for every request:**
1. Load the relevant reference file(s).
2. Pass all Pre-write Gates.
3. Implement using the verified patterns in the reference.
4. Validate retrieval quality with `scripts/metrics.py` when ground-truth labels exist.
5. Run the `python-engineering` verification loop: `ruff check --fix && ruff format → mypy → pytest`.

---

## Pre-write Gates

All must pass before writing code. Stop and fix if any fails.

**Gate 1 — Symmetric tokenization (BM25)**
If the request involves BM25: the *exact same* preprocessing (lowercase, split, stop-word
removal if any, stemmer if any) must be applied to both the corpus *and* the query. A mismatch
silently kills recall. Verify symmetry before returning code.

**Gate 2 — Normalized embeddings before cosine similarity**
If using `util.cos_sim` or dot-product for ranking: pass `normalize_embeddings=True` to
`model.encode()`. On un-normalized vectors, dot product ≠ cosine similarity. This is an
invisible bug that shows up only in metric scores.

**Gate 3 — Metric convention chosen and documented**
If the request involves evaluation metrics: choose the right metric for the relevance type and
document the choice in a comment. Use this table:

| Relevance labels | Recommended metric | Rationale |
|---|---|---|
| Graded (0/1/2/3…) | NDCG@k | Weights higher relevance grades more |
| Binary (0/1) | MAP and/or P@k | Both assume relevance is binary |
| Single relevant doc | MRR | First-hit rank is the only thing that matters |
| Recall matters | Recall@k | Add alongside NDCG or MAP |

**Gate 4 — No paid inference API**
No `openai`, `cohere`, `anthropic`, or other cloud embedding/reranking calls in core
retrieval paths. All embeddings run locally via `sentence-transformers`. If the user
explicitly asks for an API integration, note the trade-off (cost, latency, online-only)
before adding it.

---

## Quick Reference — Core Patterns

### Semantic search (CPU, no API)

```python
from sentence_transformers import SentenceTransformer, util

model = SentenceTransformer("all-MiniLM-L6-v2")  # 384-dim; ~22 MB; downloads once
# normalize_embeddings=True: cosine sim = dot product on unit vectors (no extra step)
corpus_embs = model.encode(corpus, convert_to_tensor=True, normalize_embeddings=True)
query_emb  = model.encode(query,  convert_to_tensor=True, normalize_embeddings=True)

hits = util.semantic_search(query_emb, corpus_embs, top_k=5)
# hits[0] → [{"corpus_id": int, "score": float}, …] sorted by score desc
```

Load `references/semantic-search.md` for: model selection, asymmetric vs symmetric search,
`util.cos_sim`, static embeddings (model2vec) for pure-CPU speed, and the `encode_query` /
`encode_document` v5+ API.

### BM25 lexical retrieval

```python
from rank_bm25 import BM25Okapi

# SAME tokenization for corpus and queries — always
tokenized_corpus = [doc.lower().split() for doc in corpus]
bm25 = BM25Okapi(tokenized_corpus, k1=1.5, b=0.75)

tokenized_query = query.lower().split()
scores  = bm25.get_scores(tokenized_query)      # np.ndarray, one score per doc
top_n   = bm25.get_top_n(tokenized_query, corpus, n=5)  # list of top-n doc strings
```

Load `references/bm25.md` for: k1/b tuning, preprocessing with stop-words and stemming,
BM25L/BM25+ variants, and production-scale notes.

### Hybrid fusion (BM25 + semantic) via RRF

```python
from collections import defaultdict

def reciprocal_rank_fusion(*rankings: list[int], k: int = 60) -> list[int]:
    """Combine doc-ID ranking lists via Reciprocal Rank Fusion (k=60 standard)."""
    scores: dict[int, float] = defaultdict(float)
    for ranking in rankings:
        for rank, doc_id in enumerate(ranking, start=1):
            scores[doc_id] += 1.0 / (k + rank)
    return sorted(scores, key=scores.__getitem__, reverse=True)
```

Load `references/hybrid-fusion.md` for the full end-to-end pattern combining BM25 and
semantic retrievals into one fused ranking.

### Local evaluation metrics

```python
from scripts.metrics import ndcg_at_k, mean_average_precision, precision_at_k

# relevances: list of ground-truth labels in the order the retriever returned them
ndcg  = ndcg_at_k(relevances, k=10)          # NDCG@10 (exponential gains, matches sklearn)
map_  = mean_average_precision([relevances])  # MAP over one query
p_at5 = precision_at_k(relevances, k=5)      # P@5
```

See `references/evaluation.md` for convention documentation and the metric-selection table.
`scripts/metrics.py` is pure numpy — no sklearn required at runtime.

---

## Reference Files

| File | Load when… |
|---|---|
| `references/semantic-search.md` | Implementing embedding or semantic search; choosing a CPU model; using `util.semantic_search` or `util.cos_sim` |
| `references/bm25.md` | Implementing BM25 / keyword / full-text retrieval with `rank_bm25` |
| `references/hybrid-fusion.md` | Combining BM25 and semantic rankings via Reciprocal Rank Fusion |
| `references/evaluation.md` | Measuring retrieval quality with NDCG, MAP, P@k, MRR, Recall@k |
| `references/scaling.md` | **Only when explicitly asked:** FAISS/HNSW, vector DBs, cross-encoder reranking, quantization, Matryoshka, fine-tuning |

---

## Bundled Scripts

| Script | Purpose |
|---|---|
| `scripts/metrics.py` | Pure-numpy NDCG@k, MAP, P@k, MRR, Recall@k — copy into any project, zero extra deps |
| `scripts/test_metrics.py` | Pytest suite; run to verify the metrics module after any edit |

---

## Common Mistakes to Prevent

| Mistake | Symptom | Fix |
|---|---|---|
| Different tokenization for BM25 corpus vs query | Silent recall loss | Use same `.lower().split()` + same stop-words/stemmer for both |
| Cosine sim on un-normalized embeddings | Wrong similarity scores | Pass `normalize_embeddings=True` to `model.encode()` |
| Using `model.rank()` on a `SentenceTransformer` | AttributeError | `.rank()` is a `CrossEncoder` method; for bi-encoder ranking use `util.semantic_search` |
| Comparing MAP to `sklearn.average_precision_score` | Inflated or deflated MAP | sklearn computes PR-curve AP (different formula); use `scripts/metrics.py` |
| Using NDCG for binary labels without checking convention | Metric looks high; all look equal | For binary labels MAP or P@k is more discriminative; or use `gains="linear"` NDCG |
| Choosing k1 too low | TF saturates too fast | Start at k1=1.5; increase toward 2.0 for longer docs with term repetition |
