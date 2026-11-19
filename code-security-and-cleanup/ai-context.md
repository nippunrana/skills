# code-security-and-cleanup — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **Explicit invocation only (user's decision, 2026-11-19).** Keep the "run only when the user explicitly invokes it" sentence in the description. `disable-model-invocation` works only in Claude Code, so other agents rely on the words. Why: this skill makes commits.
- **Approval is opt-in.** Only items the user approves are executed. Never bring back "unless the user objects" wording.
- **No hand-estimated Maintainability Index.** Removed on 2026-11-19; never re-add it. Why: its `HV ≈ LOC * 5` shortcut made the index follow file length, and a real audit report said so.
- **`strict_types` is not a security control.** Never list it as a fix for type juggling or tell the agent to add it to existing files. Why: it does not change how `==` works, and adding it can turn working calls into fatal errors.
- **Dead CSS, stale feature flags and duplicates are Category B.** Never move them back to Category A. Why: a search of the repository cannot see database content, a flag's real state, or whether two copies must stay separate.
- **A registered handler is never "dead".** It is unused but reachable. Removing it needs confirmation from the user or access logs.
- **OWASP labels follow the 2025 edition, with the CWE number first.** The mapping in `references/owasp-checklist.md` was checked against the OWASP 2025 category pages on 2026-11-19. Re-check it when a new edition appears. Why: the 2021 numbers had reached a client-facing report.
- **Search patterns must run under `grep -E`.** No lookaheads and no `(?:` groups. Why: those fail or misbehave on stock grep.
