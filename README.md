# Claude Skills

A collection of specialized Claude Code skills — domain-expert workflows packaged as `SKILL.md` files. Each skill is loaded by Claude Code via the Skill tool to give Claude deep, structured expertise in a specific domain.

---

## Skills

| Skill | Description |
|---|---|
| [et-frontend-design](et-frontend-design/) | Conversion-focused frontend UI with 4-phase discovery before coding |
| [skill-creator](skill-creator/) | Create, test, benchmark, and iterate on Claude skills with an evaluation loop |
| [prompt-checker-builder](prompt-checker-builder/) | Pre-flight prompt scoring or Socratic prompt builder |
| [debugger](debugger/) | Hypothesis-driven bug diagnosis across visual, code, API, and perf domains |
| [code-security-and-cleanup](code-security-and-cleanup/) | 8-phase audit: dead code removal + OWASP security hardening |
| [wp-block-theme](wp-block-theme/) | WordPress FSE block theme development (templates, patterns, theme.json, AI client, WooCommerce) |

---

## How Skills Work

A skill is a `SKILL.md` file with YAML frontmatter and a markdown body. Claude Code loads it via the Skill tool when the user's prompt matches the skill's `description` field.

```
skill-name/
├── SKILL.md              # YAML frontmatter (name, description) + skill instructions
├── references/           # Deep-dive docs loaded on demand during skill execution
├── agents/               # Subagent instruction files
└── scripts/              # Python helpers (evals, benchmarking, packaging)
```

**Frontmatter fields:**

```yaml
---
name: skill-name
description: >
  Written to match realistic user prompts. This is the only field the
  routing system reads when deciding whether to activate the skill.
---
```

**Layered loading:** The `description` routes the request. The `SKILL.md` body provides the core workflow. Reference files in `references/` are loaded on demand for depth — keeping the primary file lean and focused.

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
8-phase audit producing an executive-ready report. Classifies dead code into Category A (verified safe to delete) and Category B (needs review), then runs an OWASP-aligned security scan (hardcoded secrets, injection, XSS, SQL injection, CSRF, debug endpoints). Executes changes as atomic git commits and delivers a before/after scorecard.

**Triggers on:** clean up code, dead code, unused code, security audit, OWASP, remove deprecated, technical debt

---

### wp-block-theme
Expert WordPress FSE theme and plugin development. Covers the full HTML→template conversion workflow, custom page templates, template parts (thin HTML pointer + PHP backing pattern), block patterns with CSS scoping, theme.json design system (v3 schema), PHP-only auto-registered blocks, Block Hooks, the WP AI Client API, Abilities API, DataViews admin screens, and WooCommerce block overrides.

Uses context variables (`{{THEME_SLUG}}`, `{{THEME_NAME}}`, `{{TEXT_DOMAIN}}`, `{{PARENT_THEME_SLUG}}`) resolved from an `AI_CONTEXT.md` file in the theme directory.

**Triggers on:** WordPress theme, FSE, block theme, theme.json, block pattern, WooCommerce, WP AI client, DataViews

---

## Evaluation Framework

The `skill-creator` skill ships a complete evaluation pipeline for measuring skill quality.

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

### Optimizing a skill's description

The description field is the only routing signal. To improve it:

```bash
python skill-creator/scripts/run_loop.py --skill-dir path/to/skill/
```

This iteratively evaluates triggering accuracy and rewrites the description until it converges.

---

## Adding a New Skill

1. Create a directory: `skill-name/`
2. Write `SKILL.md` with frontmatter (`name`, `description`) and the workflow body
3. Add focused reference files to `references/` — one file per domain
4. Write 2–3 test cases in `evals/evals.json`
5. Run the eval pipeline and fix issues
6. Run `run_loop.py` to optimize the description
7. Package with `python skill-creator/scripts/package_skill.py --skill-dir skill-name/`

**Design rules:**
- Skills must be self-contained — no shared code or imports across skill directories
- Output must be immediately deployable — no placeholders, no hedging
- Reference files load on demand; keep the SKILL.md body focused on workflow
- Measurement-backed skills (debugger, skill-creator, security) enforce data-before-fix discipline

---

## Repository Conventions

- All Python scripts use `#!/usr/bin/env python3` and are executable
- Block style variation slugs use kebab-case (e.g., `is-style-hero-section`)
- WordPress template registration uses double-slash namespace (`{{THEME_SLUG}}//template_name`)
- CSS in block patterns is strictly scoped to variation classes to prevent editor UI pollution
