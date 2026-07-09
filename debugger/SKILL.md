---
name: debugger
description: Hypothesis-driven multi-domain debugger that finds the ROOT CAUSE of any bug — visual/CSS layout, code logic, async/race conditions, API/network failures, backend errors, performance issues, build/tooling problems. Use this skill whenever the user is stuck on a bug or unexpected behavior, regardless of stack or language. The skill auto-routes based on chat context, picks the lightest-weight probe (paste-ready browser-console snippet, injected debug instrumentation in source, or both), follows a strict phase-gated scientific workflow (observe → hypothesize → probe → measure → confirm → fix → cleanup), and auto-removes every line of debug code it injects so nothing leaks into production. Trigger this skill aggressively — for phrases like "why isn't this working", "this is broken", "weird bug", "the page looks wrong", "my API returns 500" — not only when the user literally says "debug".
---

# Debugger

A general-purpose, hypothesis-driven debugging assistant. You generate the *cheapest probe that can disprove the leading hypothesis*, collect data, narrow the infection chain, and fix the bug at its root — then clean up after yourself.

This skill is designed around the scientific method (Zeller, *Why Programs Fail*): a bug is a deviation between expected and observed behavior, and finding the root cause means tracing the **infection chain** from the visible failure back to the defect that started it. Guesswork is not allowed — every fix must be backed by data.

## Why this matters

LLMs often "fix" bugs by pattern-matching on symptoms — adding null checks, wrapping things in try/catch, tweaking CSS until things look right. That makes bugs vanish without making them *understood*. This skill forces you to identify the actual cause before changing anything, which:

- Prevents the same bug from coming back in a different form
- Avoids piling on defensive code that adds complexity without value
- Builds a reusable test/regression case for every fix

---

## Part A — Read context, align with global rules & route to a domain

Before generating any output:
1. **Project context check:** Look for `ai-context.md` or `AGENTS.md` in the project root. Read them to check if there are custom logging setups or diagnostic commands before proceeding.
2. **Scan:** Silently scan the conversation and the user's open files for:
   - **The symptom** — what is wrong, exactly? Wrong output? Wrong layout? No response? Slow? Crashes?
   - **The expectation** — what should happen instead?
   - **Locality hints** — file paths, class names, function names, endpoints, route patterns, error messages, framework names
   - **Stack inference** — frontend/backend/full-stack/WordPress/Node/Python/PHP/etc. (from file extensions, imports, open IDE files, mentions)

Then classify the bug into **one primary domain**. Each domain has a dedicated reference file with diagnostic patterns, snippet templates, and signals to look for:

| Domain | When to choose | Reference |
|---|---|---|
| **visual-ui** | Layout/CSS issues, misalignment, z-index, responsive breakage, WordPress style cascade, visual mismatch | `references/visual-ui.md` |
| **code-logic** | Wrong return value, off-by-one, conditional fails, state-management glitch, async/race condition, infinite loop, useEffect firing twice | `references/code-logic.md` |
| **api-network** | Failing fetch/AJAX/cURL, wrong payload shape, CORS, timeouts, 4xx/5xx, server-side route handler, DB query returning wrong rows | `references/api-network.md` |
| **perf-build** | Slow renders, jank, memory leaks, hot loops, plus Webpack/Vite errors, missing modules, env-var misconfig, build fatals | `references/perf-build.md` |

**Routing rules:**
- If the context is **clear** → pick the domain silently and proceed to Part B.
- If **two domains apply** (e.g., "API returns wrong data AND the UI doesn't render it") → start with the *upstream* domain. Fix the cause before the consequence.
- If the context is **unclear** → ask the user which domain applies, offering the four domains above as options. Don't guess.

---

## Part B — Run the phase-gated workflow

Every debugging session walks through these seven phases. Each phase has a logical checkpoint.

> **Fast-track bypass:** Skip the 7 phases only when the observation *deterministically* names the defect — a syntax error, a typo the compiler/linter points at, a missing import, an unresolved merge-conflict marker — i.e., there is no hypothesis to form because nothing needs disproving. In that case: fix it directly, verify (rerun the build/lint/test that surfaced it), and state the one-line cause. If forming the fix requires any inference about *why* the value is wrong, it is not trivial — run the phases. This is narrower than the Phase 1a shortcut below: Phase 1a still routes observed signals through Phase 5's confirm-or-refute step (which treats the signal as a confirmed finding rather than matching it against ranked hypotheses); here there's nothing to match at all, because the defect is named outright. **Exception:** if your platform's enforced plan/approval mode (a mode that blocks edits until the user approves a plan — not merely a habit of writing plans for non-trivial tasks) is active, that approval gate still applies — describe the trivial fix in the plan and get approval rather than applying it directly.

> [!IMPORTANT]
> **Planning Mode Compliance:** 
> - If you are in Planning Mode, you must draft your platform's plan document first. 
> - Define the **Hypothesis (Phase 2)** and the proposed **Probe (Phase 3)** inside that plan, as a dedicated "Debugging Hypothesis & Probes" section — placed wherever your platform's required plan structure allows. If your platform mandates specific headings for the plan document, follow that layout; don't break it. 
> - Request approval to inject the probe. Once approved, execute the probe, collect data, and update the plan with the final fix. Do not make unapproved source edits.
> - Only if you are *not* in Planning Mode may you proceed through multiple phases autonomously in a single execution loop — this includes Phase 4's "execute it yourself" instruction below, which is subject to this approval gate whenever Planning Mode is active.

### Phase 1 — Observe

**1a — Check existing signals first (before adding anything new)**

Probes are for filling gaps in what's already observable. Before generating any instrumentation, scan what's already there:

- Browser console — any errors, warnings, or logged values?
- Network tab — any failed requests, unexpected status codes, wrong payloads?
- Server / application logs — tail the log file or check the log aggregator (Sentry, Datadog, Papertrail, `wp-content/debug.log`, etc.)
- Existing monitoring dashboards — error rate spike? Latency anomaly?

If existing signals already identify the failure point, still state the one-line symptom + expectation (1c) before jumping — the delta check (1b) may be skipped. Then go directly to Phase 5 with that data. Don't add probes for things you can already see.

**1b — Delta check: is this a regression?**

Ask or infer: did this ever work? If yes, narrow the window immediately by reading recent changes (do not check out or modify the git tree):

```bash
git log --oneline -20          # what shipped recently?
git diff HEAD~5 -- package.json composer.json requirements.txt  # dependency bumps?
```

If it's a confirmed regression with a reliable reproduction step, inspecting the recent commit diffs (`git log -p`) or path-specific diffs (`git diff HEAD~5 -- path/to/file`) helps spot the defect by direct reading — see `references/code-logic.md` for the workflow.

**1c — State the symptom and expectation**

Now state both in one sentence each, in your own words. Vague problems lead to vague fixes.

> Symptom: The Save button stays disabled even after all required fields are filled.
> Expectation: It should enable as soon as the form is valid.

**Checkpoint:** existing signals checked, delta check done (or explicitly skipped per 1a's fast path), symptom + expectation written. User confirms (implicitly via continuing, or explicitly).

### Phase 2 — Hypothesize
List **2–3 ranked hypotheses** for the root cause. For each, name the **cheapest probe** that could disprove it.

> H1 (most likely): the validation function returns false because one field uses a stale ref. Probe: log the field values + validation result on each keystroke.
> H2: the disabled prop is bound to an unrelated piece of state. Probe: log the disabled prop's source on render.
> H3: a parent component is re-rendering and resetting the form state. Probe: render-count counter on the parent.

Cheapest = least invasive, fastest to run, easiest to interpret. Console snippets are cheaper than source edits. Source edits are cheaper than database changes.

**Checkpoint:** ≥ 2 hypotheses listed with probes, top one chosen.

### Phase 3 — Probe
Generate the instrumentation for the chosen probe. Decision rules below ("Part C — Pick the probe mode") tell you whether to use a console snippet, inject debug code into source files, or both.

If you inject code, every line MUST follow the tagging protocol in `references/instrumentation-protocol.md` so it can be cleanly removed later. Do not format, refactor, or touch adjacent lines when injecting debug code.

**Checkpoint:** for a User Execution probe (Phase 4), the artifact is shown to the user with clear "what this collects" + "how to use" instructions (Part E). For an Agentic Execution probe, it's injected or run directly — no user-facing presentation is required.

### Phase 4 — Collect

- **Agentic Execution (Backend/Server/Build):** If the probe requires running a shell command, running a test, or reading a server log, **DO NOT ask the user to do it** *(unless Planning Mode requires approval first — see the callout above)*. Execute it yourself using your native tools (e.g., your shell tool, your code-search tool), analyze the output autonomously, and skip Part E — it only applies to the User Execution path below.
- **User Execution (Browser/Client-side):** If the probe requires running a snippet in the Browser DevTools console, or interacting with the live UI, first check whether you have a browser-automation tool available (e.g., a Chrome DevTools or Playwright MCP/plugin). If yes, execute the snippet yourself through that tool, analyze the output autonomously, and treat this as Agentic Execution (skip Part E). If no such tool is available, you cannot do this yourself — you **MUST** use Part E to present the snippet to the user and wait for them to paste the output back. If the output is missing or noisy, refine the probe before moving on.

**Checkpoint:** usable data received (collected directly or pasted by user).

### Phase 5 — Confirm or refute

If you arrived here directly from Phase 1a (existing signals already showed the failure point, no hypotheses were ranked), treat that observed signal as your confirmed finding and go straight to tracing the infection chain upstream, below. Otherwise, match the data against the ranked hypotheses:

- **Confirmed?** Trace the infection chain *upstream*. The first wrong value is closer to the root than the visible failure. Keep going until you find the defect that caused it.
- **Refuted?** Discard the hypothesis immediately — don't add a second probe trying to rescue it. State in one line what you now know to be true (e.g., "the validation function is *not* using stale refs — values match on every keystroke"), then pivot to H2 or form a fresh hypothesis from the new evidence. Loop back to Phase 3.

**Don't fix yet.** Premature fixes are how patches end up next to bugs instead of replacing them.

**Checkpoint:** root cause identified — a specific file, line, and reason.

### Phase 6 — Fix

**Test first (if a test suite exists).** Write the regression test *before* touching production code. Run it and confirm it fails *for the reason your root-cause analysis predicted* — a test that fails for the wrong reason is worse than no test. Only then apply the fix.

**Fix at the root, not the symptom.** If the validation function is wrong, fix the validation function — don't add an extra setState in the parent. If you find yourself wrapping the visible failure in defensive code, you haven't found the root yet; return to Phase 5.

**Address the design root for recurring bug classes.** The infection chain (Phase 5) finds *the first wrong value*. For severe, architectural, or "this keeps happening in different forms" bugs, ask one more question: *why was that value allowed to be wrong in the first place?* If the answer points at shared mutable state, a missing invariant, a leaky abstraction, or an implicit contract, the design root is where the fix belongs — not the data root. Patching only the data root means the bug will resurface under a different symptom.

**Checkpoint:** failing test written and confirmed failing (when a test runner exists — otherwise the fix verified by directly exercising the code); fix applied; test now passes; for architectural bugs, the design-level cause is named even if a follow-up issue is filed rather than fixed in this pass.

### Phase 7 — Cleanup (NEVER SKIP THIS)
Remove every line of debug instrumentation injected during Phases 3–5. 
1. Use the debug ledger (see `references/instrumentation-protocol.md`) to find them.
2. **Clean up orphaned imports:** Ensure any helper libraries (e.g. `import json` or framework utils) imported at the top of the file solely for the probe are also removed.
3. Verify using your native codebase search/find tool for the `[DEBUG-` tag across the workspace — see the patterns in `references/instrumentation-protocol.md`. Use your platform's built-in search tool if available; otherwise standard utilities like `grep`/`ripgrep` are a fine fallback.

The search must return **zero matches** in source files (matches inside documentation/skill files, or probes the user chose to keep per the recovery flow, are excluded and should be listed, not removed). Console snippets are discarded; the ledger is marked CLOSED (or PARTIAL, if any probes were intentionally kept) per the protocol.

**Checkpoint:** search returns nothing. Tell the user "all debug instrumentation removed."

---

## Part C — Pick the probe mode

In Phase 3, choose how to deliver the instrumentation:

| Situation | Mode |
|---|---|
| Bug reproducible at will with an IDE/debugger attached, or reproducible by a failing test | **Interactive debugger (breakpoint + watch) or failing-test-first** — no source edits, no cleanup needed; see `references/code-logic.md` §3. Prefer this over snippets/injection when available. You can't drive the user's IDE debugger yourself: walk them through setting the breakpoint/watch and have them report back what they observe, using Part E's format (this is a User Execution probe, same as a console snippet) |
| Bug observable in the live browser without changing files (visible layout, broken click handler the user can trigger, missing element) | **Console snippet** — paste-ready, read-only, runs in DevTools |
| Bug is server-side, in async flow, or otherwise invisible from the browser (wrong DB write, race between two awaits, scheduled job misfires) | **Injected debug code** in source files, tagged per protocol |
| Bug spans browser ↔ server (API integration, auth flow, hydration mismatch) | **Both** — snippet for the client side, injected logs on the server side |
| User explicitly says "don't touch my files" or you're in a read-only environment | **Console snippet only**. If physically impossible to diagnose without source edits, stop and explain why |

You decide. The user can always override.

Note: the failing-test row needs no artifact at all — it skips Phase 3/4 entirely. The interactive-debugger row does go through Phase 3/4 (you hand the user breakpoint/watch instructions, they run it and report back) but produces no source artifact to clean up — it skips Part D and Phase 7 for that probe. For the remaining rows, the table picks the probe's *format* (console snippet vs. source injection), independent of *who runs it*. A console snippet still goes through Phase 4's User Execution path unless you have a browser-automation tool available, in which case you run it yourself per that phase's rule.

---

## Part D — Instrumentation protocol (when injecting debug code)

Read `references/instrumentation-protocol.md` for the full spec. The non-negotiables:

1. **Tag every line.** Format: `// [DEBUG-<4char-id>] <one-line purpose>` (use `# [DEBUG-<id>]` for Python/Ruby/shell, `/* [DEBUG-<id>] */` for CSS/SCSS, `<!-- [DEBUG-<id>] -->` for HTML/templates).
2. **Use the same `<id>` for one hypothesis-testing round.** All probes generated to test the same hypothesis share an id, so a single search removes them all.
3. **Maintain a debug ledger** in the conversation — a running list of `<file>:<line>: [DEBUG-<id>] <purpose>`.
4. **Never inject probes with persistent side effects** — no DB writes, no extra network calls, no re-ordered flow. In-memory interception that a refresh fully undoes (fetch wrappers, property/descriptor traps) is the one exception, per `instrumentation-protocol.md` §4.

---

## Part E — Present output to the user

This step applies only to the **User Execution** path from Phase 4 (a probe the user must run themselves — browser console snippet, manual UI interaction). Agentic probes you ran yourself skip this step per Phase 4. Whatever the mode, follow the same friendly format:

````
```<language>
<the snippet or code>
```

**What this collects:**
- <bullet 1>
- <bullet 2>

**How to use:**
1. <step 1>
2. <step 2>
3. Paste the output back here.
````

Keep it tight. One sentence per bullet. No filler.

---

## Part F — Analyze the returned data

Each domain reference file ends with a "**Signals to look for**" section. Use it. Common cross-domain signals:

- An expected log line never fires → execution doesn't reach that code path → look upstream for the gate.
- A value is `undefined`/`null` where it shouldn't be → trace where it should have been set.
- Timestamps show wrong order → race condition; you need ordering, not retry logic.
- A higher-specificity CSS rule wins → cascade conflict (see visual-ui.md).
- An API returns 200 but the DB row is missing → handler short-circuited silently; instrument the handler.

---

## Rules

- **No guess-fixes.** If you don't have data confirming the cause, don't change code. Generate another probe instead.
- **No persistent-side-effect probes.** Diagnostic code observes; it doesn't write to DB, call the network, or change flow order. (In-memory, refresh-reversible interception like fetch wrappers or property traps is the sanctioned exception — see `instrumentation-protocol.md` §4.)
- **Never leave a placeholder** like `SELECTOR`, `ENDPOINT`, or `FILE_PATH` unresolved in the artifact you hand the user. Resolve it from context first.
- **Never skip Phase 7 cleanup.** Leaked debug logs in production are a real incident risk.
- **When in doubt, ask** — a clarifying question is cheap; a wrong domain wastes the user's time.
- **Fix at the root, not the symptom.** If you find yourself adding defensive code around the visible failure, you haven't found the root yet.
- **Wrap risky access in try/catch** for browser snippets — cross-origin stylesheets, missing globals, etc., should warn, not throw.

---

## Reference files

- `references/visual-ui.md` — browser-console snippet templates for CSS, layout, cascade, visibility, responsive issues. Preserves the full toolkit from the original ui-debug-console skill.
- `references/code-logic.md` — print-trace bisection, state snapshots, async/race probes, conditional-breakpoint hints, when to use logs vs. interactive debugger vs. failing test.
- `references/api-network.md` — curl repro from network-tab data, server-log probes, DB query logging strategy (live hooks vs. read-after-execution buffers), JSON shape-diff helper.
- `references/perf-build.md` — `performance.mark/measure`, render-count counters, profiler hints, build-error triage (first error in cascade, not last), env-var diffing.
- `references/instrumentation-protocol.md` — `[DEBUG-<id>]` tag spec, ledger template, cleanup checklist.

Read the relevant domain file *before* generating the probe in Phase 3 — it has the snippet patterns and analysis signals you'll need.
