# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Repository Is

A collection of Claude Skills — specialized agents packaged as `SKILL.md` files. Each skill is a domain-expert workflow designed to be loaded by Claude Code via the Skill tool. This repo is the authoring environment for those skills.

## Skill Structure

Every skill lives in its own directory and follows this pattern:

```
skill-name/
├── SKILL.md              # Primary artifact — YAML frontmatter + markdown body
├── references/           # Deep-dive docs loaded on demand during skill execution
├── agents/               # Subagent instruction files (used by skill-creator)
└── scripts/              # Executable helpers (Python evaluation tools, etc.)
```

`SKILL.md` frontmatter fields:
- `name` — kebab-case identifier
- `description` — written to maximize correct triggering by Claude's skill-routing logic

## Skills in This Repo

| Skill | Purpose |
|---|---|
| `et-frontend-design` | Conversion-focused frontend UI with 4-phase discovery before coding |
| `skill-creator` | Create, test, benchmark, and iterate on Claude skills with an evaluation loop |
| `prompt-checker-builder` | Pre-flight prompt scoring or Socratic prompt builder |
| `debugger` | Hypothesis-driven bug diagnosis across visual/code/API/perf domains |
| `code-security-and-cleanup` | 8-phase audit: dead code removal + OWASP security hardening |
| `wp-block-theme` | WordPress FSE block theme development (templates, patterns, theme.json) |

## Skill Design Conventions

**Layered loading:** The `description` field gets loaded first (for routing). The `SKILL.md` body is the primary guidance. Reference files in `references/` are loaded on demand when specific depth is needed — keep them modular.

**Triggering:** The `description` field is the only thing the routing system reads to decide whether to activate a skill. Write it to anticipate realistic user prompts, not abstract definitions.

**Measurement-backed workflows:** The debugger, skill-creator, and code-security skills enforce a hypothesis-driven, data-before-fix protocol. New skills in these domains should maintain that discipline.

**Production output only:** Skills must produce immediately deployable artifacts — no placeholder code, no "you could also try X" hedging.

**Context variables:** The `wp-block-theme` skill uses `{{THEME_SLUG}}`, `{{THEME_NAME}}`, `{{TEXT_DOMAIN}}`, and `{{PARENT_THEME_SLUG}}` as placeholders that get resolved from an `AI_CONTEXT.md` file in the theme directory. All reference files in that skill use these placeholders — do not hardcode theme names.

## Skill Creator Evaluation Framework

The `skill-creator/` skill has a full evaluation pipeline:

- **Test cases** — 2–3 realistic prompts per skill, defined as JSON with `assertions`
- **Run evals** — parallel with-skill vs. baseline runs
- **Grade** — `agents/grader.md` scores assertions; `agents/comparator.md` does blind A/B comparison
- **Benchmark** — `scripts/aggregate_benchmark.py` aggregates pass rates and timing
- **Review** — `eval-viewer/generate_review.py` generates an interactive HTML report; open `eval-viewer/viewer.html` to browse

Eval JSON schema is documented in `skill-creator/references/schemas.md`.

## Adding or Modifying Skills

1. Keep each skill self-contained — no shared code or imports between skill directories.
2. Update the `description` field whenever the skill's trigger conditions change.
3. When adding reference files, keep each file focused on a single domain (e.g., one file per bug type, one file per CSS feature area). The skill body should cite which reference to load and when.
4. Test description triggering with the skill-creator's optimization loop (`scripts/run_loop.py`) before shipping.

## Working Style

**Think before acting.** State assumptions explicitly before editing. If a request is ambiguous, name the ambiguity and ask — don't guess. If there's more than one way to achieve a goal, present the tradeoffs rather than picking silently. Push back when a simpler approach exists.

**Simplicity first.** Write the minimum that solves the problem. No abstractions for single-use changes, no speculative features, no error handling for impossible scenarios. If a senior engineer would call it overcomplicated, simplify it. Prefer readable over clever.

**Surgical edits.** Touch only what the request requires. Don't improve adjacent code, reformat, or refactor things that aren't broken. Match existing style exactly. If your changes leave orphaned imports or unused variables, remove them — but don't remove pre-existing dead code unless asked. If you notice unrelated dead code, mention it rather than deleting it.

**Plan multi-step work.** For non-trivial tasks, state a brief plan with verification criteria before editing:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
```

**After every fix or meaningful edit**, provide a brief summary:
> **Root Cause:** what was actually wrong
> **Fix:** what changed and why it solves it
