# Mode `plan`: auditing a plan against the request and the codebase

Use this after an agent has produced an implementation plan and before any code is written. The goal is a plan that is complete relative to the request, feasible relative to the codebase, and specific enough that a later `build` audit can trace every item to code.

## Procedure

1. Gather the request, the plan, any amendments, and the project context (the context file, its built-systems table, its prohibitions, its single sources of truth).
2. Build the contract (SKILL.md, Step 0) from the plan.
3. Walk the request. Every explicit ask and every constraint becomes a row in the coverage table, mapped to the contract item that delivers it or marked MISSING.
4. Verify against the codebase. Search the project for every path, module, function, and symbol the plan names; plans invent paths and misplace files. Search for existing code that already does what a step proposes to build.
5. Apply the lenses below and collect findings.
6. Write the report and the revised contract.

## Lenses

### A. Request coverage
Every explicit ask ("add a digest command", "let the user export it") and every explicit constraint ("reuse the existing mailer", "do not change the daily email", "keep the API backward compatible") maps to at least one contract item. An ask with no item is MISSING. A step that violates a constraint is a CONFLICT and outranks everything else, because it produces working code the user did not want. If the plan drops or defers part of the request, it must say so in an explicit out-of-scope line; a silent deferral is MISSING.

### B. Silent assumptions
List every decision the plan made that the request did not specify: data shapes, allowed values, naming, defaults, error behavior, ordering, where output goes. Check each against the codebase. "Returns a dict keyed by open and closed" is an assumption about the set of ticket states; if the model defines four, the plan is wrong before it starts. Assumptions are how the wrong thing gets built correctly, so surface them for the user to confirm.

### C. Codebase fit
- **Rebuilding what exists.** For every artifact the plan proposes to create, classify it: already in the repository, provided by the standard library or an existing dependency, or genuinely new. Check the built-systems table and search for helpers with the same purpose (search by verb and noun: send mail, write csv, parse date). Only the genuinely new kind should be built; a new module that duplicates an existing utility creates two sources of truth.
- **Canonical locations.** The context file names single sources of truth. A plan that creates a parallel file (a second command module, a second config loader) instead of extending the canonical one is a finding.
- **Nonexistent paths.** Every file the plan proposes to edit must exist; every file it proposes to create must not.
- **Written prohibitions.** Anything the context file forbids that a step would do.
- **Conventions.** Naming, module layout, error handling, and testing patterns the surrounding code uses.

### D. Delivery
For each requirement, name the step that delivers it end to end. A helper that is created but never wired to a command delivers nothing; an option that is parsed but never passed on delivers nothing. Check step order for dependencies, such as a migration before the code that needs it.

### E. Verifiability
Each step carries a concrete check: a named test, a command with an expected output, an observable behavior. "Manually confirm it works" is not a check. Propose one per step; the `build` audit will later look for evidence that it ran. Steps without checks are where drift hides.

### F. Approach
Ask whether a simpler approach exists and whether the chosen one is current practice for the stack. Web research is bounded: search only when the plan hinges on a specific library, API, framework pattern, or an architectural choice with real alternatives. Two or three targeted queries, official documentation first, and note the version you relied on. Skip generic "best practices" searches; they add tokens, not information. If web access is unavailable, say so and move on.

### G. Risk
Breaking changes, data migrations, destructive operations, side effects on other features, and how to roll back. Name them. Do not pad the plan with mitigations the request does not need.

### H. Checkability
Would a `build` audit be able to trace this item to a specific change? Tighten anything it could not. "Handle errors appropriately" becomes "return a non-zero exit code and print the message when the file cannot be written". Scan the plan for placeholders: TBD, TODO, "as needed", "similar to step N", "etc.". Each one is an UNVERIFIABLE finding, because the implementing agent will fill it with a guess.

## Scope discipline

The request defines scope. A finding is **required** only when the request, one of its constraints, or a written project rule demands it. Everything else is **optional**, capped at three items, each with one line on why it is worth considering. An auditor that inflates scope makes plans balloon and defeats the simplicity the project asks for.

## Report template

Use this structure and fill every section, writing "none" where a section is empty. The example rows show the level of detail expected; they come from an unrelated project.

```markdown
# Plan Audit: <feature>

**Verdict:** <READY | NEEDS CHANGES>. <one sentence>
**Inputs:** request <in chat | pasted | path>; plan <in chat | pasted | path>; project context <found at ... | none>; web research <not needed | n queries on ...>

## Request coverage
| # | Request item or constraint | Delivered by | Status |
|---|---|---|---|
| Q1 | weekly digest email listing open tickets | Step 2, Step 3 | covered |
| Q2 | recipients can opt out from their profile | none | MISSING |
| Q3 | constraint: reuse the existing mailer | Step 1 builds a new SMTP client | CONFLICT |

## Findings (most severe first)
1. **F1 [Critical] [CONFLICT] Step 4 changes the daily email template.** The request says "do not change the daily email". Change: drop Step 4; the digest gets its own template under `emails/digest.html`.
2. **F2 [Critical] [REBUILD] Step 1 duplicates `services/mailer.py`.** It is listed as Built in the context file. Change: delete Step 1; the digest calls `mailer.send(template, recipients)`.
3. **F3 [Critical] [MISSING] No step delivers the opt-out.** Change: add a `digest_opt_out` boolean to the profile model, a migration, and a filter in the recipient query.
4. **F4 [Important] [ASSUMPTION] Ticket states are open and closed.** `models/ticket.py` defines `open`, `pending`, `resolved`, `closed`. Change: state which of these count as open and derive the filter from the model's constant.
5. **F5 [Important] [UNVERIFIABLE] Step 5 is "test it by hand".** Change: replace with the checks listed in the contract below.

## Optional suggestions (not required by the request)
- none

## Revised plan (the contract)
| ID | Requirement | Where | Verify |
|---|---|---|---|
| R1 | `build_digest(since)` returns tickets in open or pending state, newest first | `services/digest.py` | `tests/test_digest.py::test_open_and_pending_only` |
| R2 | weekly job sends the digest through `mailer.send` | `jobs/weekly.py` | `tests/test_weekly_job.py` asserts one `mailer.send` call with the digest template |
| R3 | profile opt-out excludes the user from recipients | `models/profile.py`, migration, `services/digest.py` | `tests/test_digest.py::test_opted_out_user_excluded` |

## Paste-ready summary for the planning chat
<only when run in a separate chat: the findings' "Change:" lines and the revised contract, nothing else>
```

In the same chat, once the user confirms the findings, revise the plan document to match the contract. Do not start implementing.
