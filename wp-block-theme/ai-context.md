# wp-block-theme — maintainer notes

Notes for editing this skill. They are not part of the skill: `SKILL.md` never links here. If you are using the skill on another project, ignore this file.

- **Repo-wide rules** are in `../ai-context.md`. Read it before editing. If you cannot open it (only this folder is open), say so and ask the user to add the parent folder. Do not guess the rules.
- **Record this skill's new decisions here**, as bans or decisions with their reason. Never create a `docs/` folder inside this skill.

## Decisions
- **Verify every WordPress claim against two sources**: the tagged core source (`git clone --depth 1 --branch <tag> https://github.com/WordPress/WordPress.git` into a scratch folder outside this repo, then grep only) and an official page (Make/Core dev note, Theme Handbook, Developer Blog). Why: source reading alone misdescribed `style_handle`, because that bug lives in hook timing, not in the function body. PHP facts come from php.net migration pages; WooCommerce facts from the released build under `plugins.svn.wordpress.org/woocommerce/tags/<version>/`.
- **Where official docs and the released source disagree, the skill follows the source.** Three known cases; never "correct" them to match the docs:
  1. Checkout observers sit on `wc.blocksCheckoutEvents.checkoutEvents`, not on the global itself.
  2. A script module is importable by name only when an enqueued script declares it as a dependency; enqueuing `@wordpress/abilities` alone does not work.
  3. Calling `registerCheckoutFilters()` twice with one namespace replaces the filters; it does not merge them.
- **One home per topic:** Abilities only in `references/abilities-api.md`, the AI Client only in `references/ai-client.md`.
- **`references/api-allowlist.md` is the checked list of 6.5–7.1-specific names**, not a whitelist of every WordPress function. Its last section lists names that look real but are not; never remove an entry without re-verifying it against source.
- **After editing PHP examples, lint them**: extract each php fence to its own file, substitute the placeholders, run `php -l` on PHP 8.2, 8.4 and 8.5 with no network access, and plant one broken file as a control. Running containers needs the user's approval each session.
- **Eval 2 expects the template-part backing pattern to carry `Inserter: false`.** It was aligned to the skill's rule by an agent, not stated by the user; change both together.
