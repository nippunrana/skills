# AI Context — Skills Repository

## 1. Overview & Tech Stack
- A personal library of AI agent skills in the open Agent Skills format (agentskills.io): each top-level folder holds `SKILL.md` (frontmatter `name` + `description`, then instructions), optional `references/`, and `evals/evals.json`.
- Used across agents: Claude Code, Antigravity, and others. Content is Markdown; there is no build step and no CI.
- `name` must match the folder name.

## 2. How the Skills Are Used — read before changing descriptions or structure
- **The user attaches skills by hand**, by pasting the full path of a `SKILL.md` or with `/skill-name`. Automatic triggering is neither relied on nor wanted.
- **Descriptions stay at 50 words or fewer** and say what the skill is for. Never add trigger-phrase lists or keyword stuffing, and never run skill-creator's description-optimisation loop (`scripts/run_loop.py`) on these skills. Why: Antigravity puts every description in context on every turn, and the loop optimises for auto-triggering, the opposite of how these skills are used.
- **No skill names, points to, or loads another skill.** Why: the user combines skills per prompt by hand; a cross-reference makes one skill pull in another.
- **Platform-agnostic instructions.** Never use one agent's tool names (`view_file`, `run_command`, `Read tool`, …). Describe actions logically ("read the file", "run a command") or name POSIX tools (cat, grep, git). Claude-only frontmatter (`disable-model-invocation`, `argument-hint`) is allowed only when the description also states the behaviour in words, because other agents ignore those keys (see `plan-auditor`).
- **A ban lives in `SKILL.md`, not only in a reference.** Why: skills tell agents to read only the references they need, so a ban kept only in a reference gets skipped. This happened with the font limit in `et-frontend-design`.
- **Keep each `SKILL.md` under 500 lines** (Agent Skills and Anthropic guidance); move depth into `references/`, linked directly from `SKILL.md`. Slim a `SKILL.md` only with a before/after eval run, and never move a ban out of `SKILL.md` while doing so.

## 3. Single Sources of Truth
- **Maintenance notes for one skill:** `<skill>/ai-context.md`. Read it before editing that skill and record that skill's new decisions there.
- **Runtime rules for one skill:** its `SKILL.md`; `references/` carry the depth.
- **Eval cases:** `<skill>/evals/evals.json`. Eval schemas: `skill-creator/references/schemas.md` (local only, see §5).
- **Repo-wide rules:** this file.

**Decision:** skill decisions live in `<skill>/ai-context.md`, not in a root `docs/built-systems/` folder. Why: skills are often edited with only the skill's folder open as the workspace, and the agent then reads only that folder's `ai-context.md`. Never create a `docs/` folder inside a skill.

## 4. Built Systems vs Roadmap
| Skill | Status | `evals/evals.json` |
| :--- | :--- | :--- |
| `code-security-and-cleanup` | Built | Yes |
| `debugger` | Built | Roadmap |
| `et-copywriting` | Built | Yes |
| `et-frontend-design` | Built | Roadmap |
| `gemini-integration` | Built | Yes |
| `gsap-animation` | Built | Yes |
| `plan-auditor` | Built | Yes |
| `prompt-checker-builder` | Built | Yes |
| `python-engineering` | Built | Yes |
| `shopify-theme` | Built | Yes |
| `wp-block-theme` | Built | Yes |
| `skill-creator` | Local only — copy of Anthropic's skill-creator, gitignored | — |

## 5. Environment & Deployment Constraints
- **This folder is Antigravity's global skills folder**: `~/.gemini/config/skills` is a symlink to it. Every saved edit is live in Antigravity at once, including half-finished ones.
- **Any top-level folder containing `SKILL.md` is a live global skill.** Never keep skill copies, snapshots, or cloned skill repos inside this repo; Antigravity's scan depth is undocumented, so nested copies may load as duplicate skills.
- **Eval workspaces go outside this repo** (for example `~/skill-workspaces/<skill>-workspace/`). skill-creator's default puts `<skill>-workspace/` beside the skill and copies `SKILL.md` into `skill-snapshot/`, which would place a duplicate skill inside this folder.
- **Claude Code has no installed copy** of these skills (`~/.claude/skills` holds none), so nothing needs syncing there.
- **Gitignored, so absent from any clone:** `skill-creator/`, `*-workspace/`, `temp/`, `.claude/`. Never state that the repo ships skill-creator.
- **`plan-auditor/evals/fixtures/**/ai-context.md` is test data**, not context for this repo.
- **Each `<skill>/ai-context.md` ships inside the skill folder.** `SKILL.md` must never link to it, so agents using the skill never load it.

## 6. Development Guidelines
1. **Self-contained:** no shared code, imports, or files across skill folders.
2. **Deployable output:** code a skill tells the agent to produce has no `TODO`, `YOUR_CODE_HERE`, or invented endpoints. `{{THEME_SLUG}}`-style tokens inside skill templates are deliberate; the agent substitutes them when the skill runs.
3. **Data before fix:** `debugger` and `code-security-and-cleanup` gather data and confirm a hypothesis before changing code. Never add a shortcut that skips this.
4. **Read-only git in diagnostic and review skills:** `debugger` and `plan-auditor` never check out, stash, stage, commit, or reset. (`code-security-and-cleanup` makes commits on purpose; the rule is not repo-wide.)
5. **Evals:** a new or substantially changed skill gets at least three eval cases in `evals/evals.json` and a skill-creator eval run before it is called done.
6. **New skill checklist:** folder name equals `name`; description of 50 words or fewer; `SKILL.md` under 500 lines; `<skill>/ai-context.md` created from the pattern of an existing one; a row added to §4 and to `README.md`.
