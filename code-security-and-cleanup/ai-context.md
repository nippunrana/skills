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

## Evals
- **Evals run on generated git fixtures.** `evals/fixtures/setup.sh <scenario> <dest> [state-dir]` builds a fresh repository for one run and writes its state folder (default `<dest>.state/`); `evals/fixtures/check.sh` reads it, from `STATE_DIR` when that is set.
- **A run's own folder holds only the task, the repository and the outputs folder.** Keep the state folder, the assertions and the check command somewhere the agent does not list. Why: in the first baseline runs they sat beside the task, and the state folder's file names alone tell an agent it is being tested.
- **Never store a secret-shaped string, a vulnerable version pin, or a file the root `.gitignore` drops inside a fixture.** `setup.sh` generates them. Why: this repo is public, so a stored key can trip push protection and a stored pin can raise dependency alerts; ignored files would silently go missing from a clone.
- **Fixture file names, function names and comments carry no hints.** No "legacy", "deprecated", "unused" or similar. Why: a name that gives a planted item away makes the eval measure reading names.
- **A run must use a copy of the skill without `evals/`.** Why: that folder holds the planted issues, the reference fixes and the assertions.
- **Examples in `SKILL.md` and `references/` must not reuse a fixture's planted issues.** Why: the evals would then measure recall of the examples.
- **Run `evals/fixtures/selftest.sh` after changing any fixture or check;** it must end with 0 wrong. Set `PHP_DOCKER_IMAGE` (or have `php` on PATH) so the PHP parse check and the stub run are included.
- **Check names start with `safety_`, `rule_` or `target_`.** Keep a safety failure (true for any agent) apart from a skill rule. Why: a no-skill baseline should not be marked unsafe for skipping a rule only the skill states.
- **The baseline "old skill" is commit `60475b2`,** the version before the 2026-11-19 corrections.
- **Detection is at ceiling on these fixtures.** In the 2026-11-19 baseline (Fable 5.2 at max effort with an advisor model and the user's global rules loaded), no skill and the old skill found every planted security issue in evals 0 and 4. A weaker setup may not be at ceiling. Never claim a detection gain from these evals; that needs harder seeds. Why: a perfect score here shows the model, not the skill.
- **Weak seed: the plugin's `./polyfills.js` import.** Agents reasonably suggest dropping polyfills that current browsers no longer need, so eval 0's assertion 18 failed in 5 of 6 baseline runs in both setups. When the plugin fixture is next changed, replace it with a side-effect import the plugin plainly needs, and re-run the baseline for evals 0 and 1.
