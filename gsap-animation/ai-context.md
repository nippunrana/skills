# gsap-animation — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **The LCP ban covers the LCP element only** (hero heading or hero image). Never widen it to "all hero elements" or "everything above the fold": other hero elements may still fade from 0.
- **Hero snippets contain no ScrollTrigger and no `gsap.from()`.** The three-tier loading model in `references/loading.md` depends on the hero chunk carrying GSAP core only.
- **Keep the `matchMedia` reduced-motion warning in both `SKILL.md` and `references/loading.md`.** Eval runs found the trap; a reference-only warning gets skipped.
- **After changing load-time guidance, re-run evals 1, 4 and 5.** They are the evals that separate correct LCP handling from incorrect.
