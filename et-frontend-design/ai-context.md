# et-frontend-design — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **At most two rendered font families per page, counting theme, icon, monospace and system fonts.** The user required this after a page built with the skill loaded four fonts. A monospace exemption for `<code>` was offered and not accepted; never add it without asking.
- **The two-family ban stays in `SKILL.md`**, not only in a reference. Why: `SKILL.md` tells the agent to read only necessary references, so the reference-only ban was skipped while an always-read reference prescribed three families.
- **`references/typography.md` is canonical** for font count, weight budget, the font-psychology research, and the selection workflow. Other references must not prescribe their own family count, and no example recipe may put more than two families on a page.
- **Keep the research labels** in `typography.md`: findings are split into robust / plausible / debunked, and the industry-to-font mapping is labelled craft convention, not research.
