# debugger — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **Never add `git bisect` or any step that checks out old commits.** Regressions are narrowed with read-only `git log` / `git diff`. Why: a diagnosis must not change the user's working tree; the skill was moved off bisect on purpose.
