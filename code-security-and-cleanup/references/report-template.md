# Report Templates

Follow the project's or the user's own report format if they have one; otherwise use these. Leave out a section that has nothing in it, except **Coverage and limits**, which is always present.

The report is sensitive: it describes weaknesses. Write it only where the user says, never into a web-served folder, and never commit it.

## Audit report

```markdown
# <Project>: security and dead-code audit, <date>

Scope: <full | diff: what changed | paths>. Lens: <security | dead code | both>.
Reviewed at: <branch> @ <short hash>, <clean tree | uncommitted changes in: files>.

## Summary
3 to 5 sentences: what was reviewed; counts by verdict and severity; the most urgent action;
the main thing that was not checked. Never "secure" or "clean".

## Threat model
Who uses it, where input enters, what is worth protecting, protections already in place.

## Confirmed findings
| ID | Severity | Finding | Label | Location | Who can trigger it |
| :--- | :--- | :--- | :--- | :--- | :--- |
| S1 | High | ... | CWE-… · A0x:2025 … | file, function, line | any logged-in user |

### S1: <title>
- Trace: <source> → <protections, and why they fail> → <sink, file:line>
- Impact: <what the attacker gains>
- Confidence: <high | medium | low>
- Fix: <smallest change, using the project's own helpers>
- How to verify the fix: <test or request that fails now and passes after>

## Needs validation
| ID | Suspected problem | Location | Blocker | What would settle it |

## Hardening
| ID | Item | Where | Note (for example "confirm at the server") |

## Dead code
### Proposed for removal (REMOVE)
| ID | Item | Location | Evidence |
### Needs a decision (REVIEW)
| ID | Item | Location | Why unsure | What would settle it |

## Performance risks (unmeasured)
At most five, only if noticed while reading. Each one says it was not measured.

## Checked and found clean
One line each: what was looked at, and the protection that holds.

## Coverage and limits
- Reviewed: <files or entry points>. Not reviewed: <what, and why>.
- Commands and tools run: <command: result>.
- Not run: <dependency audit, secret scan of history, tests, parse checks, the app itself>, and why.
- Cannot be seen from the repository: <server and CDN config, database content, live traffic, secrets in deployment settings>.

## Decisions needed
Which IDs to apply, and any question that blocks a verdict.
```

## Outcome report (after applying)

```markdown
# <Project>: changes applied, <date>

Branch: <new branch>, from <original branch> @ <short hash>. Not pushed.

## Applied
| ID | Commit | Change | Checks run and result |

## What testing showed that reading had not
Anything a check or a reproduction revealed: a finding worse or milder than it read, a removal that broke something.

## Before and after
Only figures from running the same command before and after: `git diff --shortstat <base>..HEAD`,
test results, a tool's finding count.

## Not run, or untested
Each check that could not run, the changes it leaves untested, and the command that would check them.

## Still open
Findings not approved or not fixed, REVIEW items, and reverted items with the reason.

## Noticed in passing
At most five problems outside the scope. Nothing here was changed.
```
