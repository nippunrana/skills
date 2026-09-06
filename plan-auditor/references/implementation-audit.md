# Mode `build`: auditing an implementation against the plan

Use this after an agent has implemented a plan and before the work is accepted or committed. The goal is a traceability table backed by evidence and a remediation list the implementing agent can act on without this conversation.

## Procedure

### 1. Build the contract first
Do Step 0 from SKILL.md before reading any change. The contract is the target; the diff must not be allowed to redefine it.

### 2. Collect the change set (read-only)
Run from the repository root:

```bash
git status --porcelain=v1 -uall            # every changed and new file, untracked included
git diff                                   # unstaged changes
git diff --cached                          # staged changes
git ls-files --others --exclude-standard   # untracked files: read each one in full
```

- If the user says the work was committed, find the base with `git log --oneline -15` and take `git diff <base>...HEAD`, then still run the four commands above for anything not yet committed.
- Never checkout, stash, reset, clean, or commit. The audit leaves the repository exactly as it found it.
- Large change sets: list the files first, then read them one at a time. Every changed file gets read, untracked ones included, because unplanned changes hide in the files that are skipped.
- Not a git repository: audit the files the chat or the plan names and state the coverage limit at the top of the report.
- Empty change set: stop and ask whether the work was committed, stashed, or made in another checkout. Do not audit against nothing.

### 3. Forward trace: contract to change
For each `Rn`, find the change that implements it and assign a status from the vocabulary in SKILL.md. Evidence is a file plus a function or line region. Read the implementation, not just the diff header: a function that exists but ignores one of its arguments is PARTIAL or MISSING for the requirement that argument serves. If almost nothing in the change set relates to the contract, stop and confirm with the user that the plan and the repository belong together before writing the report.

### 4. Reverse trace: change to contract
For each changed file and hunk, name the contract item it serves. A hunk that serves none is UNPLANNED. Classify it:

- **Glue**: imports, registrations, and wiring that a planned item needs. Note it; no finding.
- **Drive-by**: renames, refactors, reformatting, "tidying" of code the plan never mentioned. A finding, because it widens the review surface and breaks callers (see the catalog below). This is also where the project's surgical-changes rule gets enforced.

### 5. Integration checks
Run each of these against the change set. Each row says what to search for and what a hit means.

| Check | How | A hit means |
|---|---|---|
| Wiring | For each new function, class, route, hook, component, or subcommand, search the project for references outside its definition. | Zero references: defined but unreachable. |
| Name agreement | For each call into new or changed code, search the defining module for the exact called name and confirm the signature. | Mismatch: fails at runtime, often only on the path nobody ran. |
| Callers of renames and signature changes | Search the whole project (tests, templates, configs, docs) for the old name and the old call shape. | Any hit: a broken caller. |
| Duplicate helpers | For each new helper, search for an existing one with the same purpose and check the built-systems table. | Two sources of truth. |
| Cross-boundary contracts | Compare field names, types, and shapes on both sides of every boundary the change touches: API and client, model and serializer, form and handler, CLI option and function parameter. | Mismatch: silent data loss or wrong behavior. |
| Registration | Routes registered, migrations listed, env vars both read and documented, new modules exported where consumers expect them, schemas and config updated. | Feature exists but is not reachable or not configured. |
| Convention drift | New code uses a pattern the codebase avoids (direct output instead of logging, a hand-rolled writer instead of the shared helper, raw requests instead of the API client). | Check against written prohibitions; a violation is a finding even when the code works. |

### 6. Leftovers
Search the change set (not the whole project) for: debug output statements, `[DEBUG-` tags, TODO / FIXME / XXX added by this change, commented-out code, placeholder text, imports / variables / loggers introduced but unused, hard-coded test values or local paths, disabled or skipped tests, scratch files, formatting-only hunks. Pre-existing leftovers outside the change set get one line of mention, not a count, per the surgical-changes rule.

### 7. Verification evidence
For each contract item's verify step, determine whether it ran. Evidence is command output in the chat, or a test file that exists and actually exercises the behavior (a test named after the feature that checks only the response code does not cover the filter). If the project has a cheap test, lint, or type-check command, run it and quote the tail of its output. Do not write or modify tests.

### 8. Light correctness pass
Note only defects that stop a contract item from being met: an argument accepted and ignored, a wrong parameter passed, the null case the plan called out. Hand everything deeper to the project's code-review, simplification, or security tooling and say so in the report.

### 9. Write the report

## Report template

Fill every section, writing "none" where a section is empty. The example rows show the level of detail expected; they come from an unrelated project.

```markdown
# Implementation Audit: <feature>

**Verdict:** <PASS | CONCERNS | FAIL>. <n> of <m> requirements done; <n> partial, <n> missing, <n> deviated, <n> waived, <n> unverified. <n> integration conflicts, <n> leftovers, <n> unplanned changes.
**Change set:** <n> files (<staged / unstaged / untracked counts>, or <base>..HEAD). Tests: <command> gave <result, or "not run: reason">.
**Coverage limits:** <none | what could not be inspected and why>

## Traceability
| ID | Requirement | Status | Evidence | Note |
|---|---|---|---|---|
| R1 | `archiveProject(id)` sets `archived_at` and returns the project | DONE | `services/archive.ts` `archiveProject()` lines 12-31 | |
| R2 | archived projects are hidden from the default list | MISSING | `routes/projects.ts` `listProjects()` has no `archived_at` filter | chat claimed done |
| R3 | archive button confirms before calling the API | DEVIATED (silent) | `components/ArchiveButton.tsx` calls the API on first click | plan required a confirm step |
| R5 | changelog entry | WAIVED | user: "skip the changelog, I'll batch it at release" | |

## Conflicts and integration issues
1. **Route calls a function that does not exist (F2).** `routes/projects.ts` `archiveHandler()` calls `archiveProjectById`; `services/archive.ts` exports `archiveProject`. Effect: the archive endpoint fails at runtime. Fix direction: call `archiveProject`.
2. **Rename broke a caller (F4).** `utils/dates.ts` renamed `formatDate` to `fmtDate`; `components/ProjectRow.tsx` still imports `formatDate`. Effect: the build fails. Fix direction: revert the rename (unplanned) or update every caller.

## Unplanned changes
| File | Change | Classification | Recommendation |
|---|---|---|---|
| `utils/dates.ts`, `components/ProjectRow.tsx` | `formatDate` renamed to `fmtDate` | drive-by rename | revert |
| `models/project.ts` | import reordering only | drive-by reformat | revert |

## Leftovers
| File | Region | Content |
|---|---|---|
| `services/archive.ts` | `archiveProject()` | `console.log("archiving", id)`, `// TODO handle missing project`, unused `import fs` |

## Verification evidence
| ID | Planned check | Ran? | Evidence |
|---|---|---|---|
| R1 | unit test sets `archived_at` | yes | `tests/archive.test.ts::sets archived_at` present; test command output shows it passing |
| R2 | list excludes archived projects | no | no such test; suite run shows 1 failure in `ProjectRow.test.tsx` from the rename |

## Previous findings
<only on a re-audit: each earlier finding ID marked ADDRESSED or NOT ADDRESSED, with fresh evidence>

## Handoff: remediation list for the implementing agent
Each item stands alone, carries a stable finding ID and a severity, and is ordered Critical, Important, Minor.
1. **F1 [Critical] [R2] Hide archived projects from the default list.** `routes/projects.ts`, `listProjects()`: add `archived_at IS NULL` to the default query, keep `?includeArchived=true` to show them. Accept when a test lists two projects, archives one, and the default list returns one.
2. **F2 [Critical] [Conflict] Fix the archive route call.** `routes/projects.ts`, `archiveHandler()`: call `archiveProject`, the name exported by `services/archive.ts`. Accept when a request to the archive endpoint returns 200 and the project's `archived_at` is set.
3. **F4 [Critical] [Unplanned] Revert the date helper rename.** Restore `formatDate` in `utils/dates.ts` and `components/ProjectRow.tsx`. Accept when the build and the full test suite pass.
4. **F3 [Important] [R3] Add the confirmation step.** `components/ArchiveButton.tsx`: show the confirm dialog and call the API only on confirm. Accept when the component test asserts no API call before confirmation.
5. **F5 [Minor] [Leftover] Remove debug output.** `services/archive.ts`: delete the console statement and the TODO; drop the unused `fs` import. Accept when the change set contains no console output in service code.
```

In a separate chat, this whole report is the paste-ready artifact. In the same chat, hand it to the user and wait; the fixes are the implementing agent's job.
