# AI Context for Skills Repository

This repository contains a collection of production-ready AI agent skills packaged as `SKILL.md` files. These skills are domain-expert workflows that load on demand to give AI agents deep, structured expertise in specific areas.

## Core Architecture
- **Skill Format:** Every skill MUST have a `SKILL.md` file containing YAML frontmatter (`name` and `description`) followed by Markdown instructions.
- **Routing:** The `description` field in the frontmatter is the *only* routing signal used by platforms (Antigravity IDE, Claude Code, Gemini CLI, Cursor). It must be optimized to match realistic user prompts.
- **Layered Loading:** Keep the primary `SKILL.md` file focused on the workflow. Place deep-dive docs in a `references/` subdirectory to be loaded on demand. This keeps context windows efficient.

## Repository Rules & Conventions
1. **Self-Contained:** Skills must be entirely self-contained. Do not use shared code, imports, or dependencies across different skill directories.
2. **Deployable Output:** Output must be immediately deployable. Never leave placeholders like `TODO`, `YOUR_CODE_HERE`, or `ENDPOINT`.
3. **Data-Before-Fix Discipline:** Measurement-backed skills (e.g., `debugger`, `code-security-and-cleanup`) must enforce gathering data and confirming hypotheses before executing a fix. 
4. **Git Safety:** Any debugging workflows that change the git tree (such as `git bisect`) must stash local changes (`git stash`) before running and restore them (`git stash pop`) afterward.
5. **Cleanups:** Ensure all debug instrumentation (and helper imports like `import json`) are completely removed after a bug fix. Use `grep_search` to verify.

## Evaluation Framework
- The repository includes a `skill-creator` pipeline for evaluating and iterating on skills.
- Test cases live in `evals.json`.
- When adding a new skill, always write test cases and ensure the skill passes the evaluation pipeline before finalizing.

## WordPress / Frontend Conventions
- WordPress block style variation slugs must use kebab-case (e.g., `is-style-hero-section`).
- WordPress template registration uses the double-slash namespace (`{{THEME_SLUG}}//template_name`).
- CSS in block patterns must be strictly scoped to variation classes to prevent editor UI pollution.
