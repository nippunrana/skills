# AI Agent Skills

A collection of production-ready AI agent skills packaged as `SKILL.md` files. Each skill is a domain-expert workflow that loads on demand to give your AI agent deep, structured expertise in a specific area.

**Compatible with:** Claude Code · Antigravity IDE · Gemini CLI · OpenAI Codex CLI · Cursor

---

## Skills

| Skill | Description |
|---|---|
| [et-frontend-design](et-frontend-design/) | Conversion-focused frontend UI with 4-phase discovery before coding |
| [et-copywriting](et-copywriting/) | Sales-psychology copywriting: headlines, CTAs, ad copy, landing page and email copy for a persona |
| [gemini-integration](gemini-integration/) | Google Gemini and Gemma integration: SDK setup, structured output, tool calling, model discovery |
| [gsap-animation](gsap-animation/) | GSAP tweens, timelines, ScrollTrigger and framework integration, with Core Web Vitals-safe loading |
| [python-engineering](python-engineering/) | Production Python 3.10+: typing, clean architecture, scripts, and quality tooling |
| [shopify-theme](shopify-theme/) | Shopify Online Store 2.0 themes: Liquid sections, JSON templates, theme blocks, performance |
| [prompt-checker-builder](prompt-checker-builder/) | Pre-flight prompt scoring or Socratic prompt builder |
| [debugger](debugger/) | Hypothesis-driven bug diagnosis across visual, code, API, and perf domains |
| [code-security-and-cleanup](code-security-and-cleanup/) | 8-phase audit: dead code removal + OWASP security hardening |
| [wp-block-theme](wp-block-theme/) | WordPress FSE block theme development (templates, patterns, theme.json, AI client, WooCommerce) |

`skill-creator` (create, test and benchmark skills) is used locally for the evaluation pipeline below but is not included in this repository.

---

## Platform Compatibility

The `SKILL.md` format has emerged as a cross-platform standard for AI agent skills. These skills work with any agent that implements the format:

### Claude Code (Anthropic)
Load a skill via the Skill tool. The agent reads the `description` field to route requests, then loads the `SKILL.md` body on demand. Global instructions go in `~/.claude/CLAUDE.md`; project-level instructions go in `CLAUDE.md` at the repo root.

### Antigravity IDE (Google)
Google's agent-first IDE (launched November 2025) uses the same `SKILL.md` format with the same `description`-based routing. Place skills in the `.agents/skills/` directory. Antigravity also reads `GEMINI.md` as its global instruction file and `agents.md` to define agent team roles. Its multi-agent orchestration surface lets you run several skills in parallel across sandboxed agents.

### Gemini CLI
The Gemini CLI picks up skills from the local `.gemini/skills/` directory and resolves the `description` field for routing — same mechanism as Claude Code.

### OpenAI Codex CLI
Codex CLI reads `AGENTS.md` as its primary instruction file and supports the `SKILL.md` format for on-demand skill loading. Drop skills into `.codex/skills/`.

### Cursor
Cursor loads skills from `.cursor/skills/`. The description-based routing and reference file pattern work identically.

> **How these skills are used:** they are attached by hand (by `SKILL.md` path or `/skill-name`), not left to automatic triggering. Descriptions are kept to 50 words or fewer because some agents load every description into context on every turn.

---

## How Skills Work

```
skill-name/
├── SKILL.md              # YAML frontmatter (name, description) + skill instructions
├── references/           # Deep-dive docs loaded on demand during execution
├── agents/               # Subagent instruction files (grader, comparator, etc.)
└── scripts/              # Python helpers (evals, benchmarking, packaging)
```

**Frontmatter:**

```yaml
---
name: skill-name
description: >
  Written to match realistic user prompts. This is the only field the
  routing system reads when deciding whether to activate the skill.
---
```

**Layered loading:** Routing reads only `description`. The skill body provides the core workflow. Reference files in `references/` load on demand for depth — keeping the primary file focused and the context window efficient.

---

## Skill Summaries

### et-frontend-design
Converts vague design requests into production-grade UI. Runs a 4-phase discovery (Diagnose → Strategy Brief → Strategic Questions → Refine & Execute) before writing a single line of code. Applies conversion psychology, a design token system, modern CSS (container queries, scroll-driven animations, view transitions), and mobile-first responsive principles.

**Triggers on:** build, style, redesign, landing page, dashboard, component, animation, color scheme, mobile screen, Tailwind

---

### skill-creator
The full skill lifecycle: capture intent → write draft → test → evaluate → iterate. Runs parallel with-skill vs. baseline evaluations, grades assertions, aggregates benchmarks, and opens an interactive HTML reviewer for human feedback. Includes a description optimization loop (`scripts/run_loop.py`) that improves triggering accuracy iteratively.

**Triggers on:** create a skill, new skill, improve this skill, optimize skill description, evaluate skill, benchmark

---

### prompt-checker-builder
Two modes in one skill. **Checker mode:** scores an existing prompt against 7 quality dimensions (context completeness, clarity, structural integrity, output contract, task decomposition, technique fitness, few-shot examples). **Builder mode:** Socratic interview that produces a robust prompt from scratch. Always presents a plan before generating the final version.

**Triggers on:** check this prompt, evaluate my prompt, improve prompt, help me write a prompt, prompt review

---

### debugger
Scientific-method debugging with 7 phase-gated steps: observe → hypothesize → probe → measure → confirm → fix → cleanup. Routes to one of four domains (visual-ui, code-logic, api-network, perf-build) and loads the matching reference file. Injects tagged instrumentation (`[DEBUG-<id>]`) and verifies removal after the fix.

**Triggers on:** bug, error, not working, broken, unexpected behavior, visual glitch, API failure, slow, build error

---

### code-security-and-cleanup
8-phase audit producing an executive-ready report. Classifies dead code into Category A (verified dead, removed only after approval) and Category B (needs review), then runs an OWASP-aligned security scan (hardcoded secrets, injection, XSS, SQL injection, CSRF, debug endpoints). Executes approved changes as atomic git commits and delivers a before/after scorecard.

**Invocation:** explicit only. Attach the skill or name it; it never triggers on its own.

---

### wp-block-theme
Expert WordPress FSE theme and plugin development. Covers the full HTML→template conversion workflow, custom page templates, template parts (thin HTML pointer + PHP backing pattern), block patterns with CSS scoping, theme.json design system (v3 schema), PHP-only auto-registered blocks, Block Hooks, the WP AI Client API, Abilities API, DataViews admin screens, and WooCommerce block overrides.

Uses context variables (`{{THEME_SLUG}}`, `{{THEME_PREFIX}}`, `{{THEME_NAME}}`, `{{TEXT_DOMAIN}}`, `{{PARENT_THEME_SLUG}}`) resolved from an `AI_CONTEXT.md` file in the theme directory. Targets WordPress 7.0 and later on PHP 8.4 by default; 7.1-only features and PHP 8.2/8.3 fallbacks are gated on the site's actual versions, which the skill establishes first.

**Triggers on:** WordPress theme, FSE, block theme, theme.json, block pattern, WooCommerce, WP AI client, DataViews

---

## Evaluation Framework

The `skill-creator` skill (a local copy, not included in this repository) provides the evaluation pipeline for measuring skill quality.

### Running an evaluation

```bash
# Run a single test case (with-skill vs. baseline)
python skill-creator/scripts/run_eval.py --eval-file path/to/evals.json --case 0

# Aggregate results from multiple runs
python skill-creator/scripts/aggregate_benchmark.py --results-dir path/to/results/

# Generate an interactive HTML report
python skill-creator/eval-viewer/generate_review.py --benchmark path/to/benchmark.json
# Then open eval-viewer/viewer.html in a browser
```

### Evaluation files

| File | Purpose |
|---|---|
| `evals.json` | Test case definitions: prompt, files, assertions |
| `eval_metadata.json` | Per-run metadata with assertion results |
| `grading.json` | Assertion results: text, passed, evidence |
| `benchmark.json` | Aggregated pass rates, timing, token usage, deltas |
| `feedback.json` | Human reviewer feedback per run |

Full JSON schemas are documented in [skill-creator/references/schemas.md](skill-creator/references/schemas.md).

### Description optimization

Do not run skill-creator's description-optimization loop (`scripts/run_loop.py`) on these skills. It tunes descriptions for automatic triggering, and these skills are attached by hand.

---

## Adding a New Skill

1. Create a directory: `skill-name/`
2. Write `SKILL.md` with frontmatter (`name`, `description`) and the workflow body
3. Add focused reference files to `references/` — one file per domain
4. Write at least three test cases in `evals/evals.json`
5. Run the eval pipeline and fix issues
6. Add `skill-name/ai-context.md` for maintainer notes (copy the header of an existing one)
7. Package with `python skill-creator/scripts/package_skill.py --skill-dir skill-name/`

**Design rules:**
- Skills must be self-contained — no shared code or imports across skill directories
- Output must be immediately deployable — no placeholders, no hedging
- Reference files load on demand; keep the `SKILL.md` body focused on workflow
- Measurement-backed skills (debugger, skill-creator, security) enforce data-before-fix discipline
- Keep the `description` to 50 words or fewer, and never name or load another skill

---

## Repository Conventions

- All Python scripts use `#!/usr/bin/env python3` and are executable
- WordPress block style variation slugs use kebab-case (e.g., `is-style-hero-section`)
- WordPress template registration uses double-slash namespace (`{{THEME_SLUG}}//template_name`)
- CSS in block patterns is strictly scoped to variation classes to prevent editor UI pollution
