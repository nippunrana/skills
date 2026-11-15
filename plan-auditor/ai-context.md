# plan-auditor — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **Explicit invocation only (user's decision).** Never add auto-trigger wording to the description. Keep its "run only when explicitly invoked" clause: `disable-model-invocation` works only in Claude Code, so other agents rely on the words.
- **Report only.** Never give the skill a fix or edit mode. The single exception, revising the plan document in `plan` mode after the user confirms, is stated in `SKILL.md`.
- **No checklist file in the user's repo.** The user chose report output; the contract lives inside the report.
- **Evals run on git fixtures**: `evals/fixtures/setup.sh base|build <dest>` builds a fresh repo for one run. The `ai-context.md` inside `evals/fixtures/` is part of the test project, not notes for this skill.
- **Add the skill to `README.md` only after its evals pass.**
