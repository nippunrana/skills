# AI Coding Guidelines

## 1. Think Before Coding
**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- **State assumptions explicitly**: If a request is ambiguous, state your interpretation before proceeding. If uncertain, ask—don't guess.
- **Present multiple interpretations**: If there's more than one way to achieve a goal, present them with tradeoffs — don't pick silently.
- **Push back when warranted**: If a simpler approach exists or if the requested approach is suboptimal/wrong, say so. Prioritize accuracy over agreement.
- **Stop when confused**: Name exactly what is unclear and ask for clarification immediately.

---

## 2. Simplicity First
**Minimum code that solves the problem. Nothing speculative.**

- **No features beyond what was asked.**
- **No abstractions for single-use code**: Don't build "frameworks" or complex patterns for one-off tasks.
- **No speculative features**: Don't add "flexibility," "configurability," or features that weren't explicitly requested.
- **No error handling for impossible scenarios.**
- **The Senior Engineer Test**: If a senior engineer would call the code overcomplicated, simplify it. If 200 lines could be 50, rewrite it.
- **Prefer readability**: Use simple, readable code over clever one-liners or complex abstractions.

---

## 3. Surgical Changes
**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- **No orthogonal edits**: Don't "improve" adjacent code, comments, or formatting unless specifically asked.
- **Don't refactor things that aren't broken.**
- **Rule of Least Surprise**: Match the existing code style and conventions exactly. Do not introduce new patterns inconsistently.
- **Mention, don't delete**: If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:
- **Clean up your own mess**: Remove any imports, variables, or functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

**The test:** Every changed line should trace directly to the user's request.

---

## 4. Goal-Driven Execution
**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass."
- "Fix the bug" → "Write a test that reproduces it, then make it pass."
- "Refactor X" → "Ensure tests pass before and after."

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

- **Propose a brief plan first**: For non-trivial tasks, outline the steps and verification criteria before making edits.
- **Confirm the fix**: Always confirm that the change actually addresses the root cause of the problem. Don't assume it works.
- **Highlight side effects**: Explicitly call out any breaking changes, side effects, or risks before proceeding.

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

## After Every Fix or Edit
After completing any fix or code change, always provide a brief **"Root Cause & Fix"** summary:

> **Root Cause:** [1–2 plain-English sentences on what was actually wrong]
> **Fix:** [1–2 plain-English sentences on what was changed and why it solves the problem]

Keep it concise — no jargon, no lengthy explanations. Just the core insight.

---

## How to Know It's Working

These guidelines are working if you see:

- **Fewer unnecessary changes in diffs** — Only requested changes appear
- **Fewer rewrites due to overcomplication** — Code is simple the first time
- **Clarifying questions come before implementation** — Not after mistakes
- **Clean, minimal PRs** — No drive-by refactoring or "improvements"