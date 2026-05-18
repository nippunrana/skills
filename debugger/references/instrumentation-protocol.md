# Instrumentation Protocol

Whenever the debugger skill injects debug code into source files (rather than just generating a one-shot console snippet), it MUST follow this protocol. The point is simple: no debug instrumentation ever survives past the diagnosis. Leaked `console.log` lines and `error_log` calls in production are a real incident risk — they leak data, clutter logs, and can mask future bugs.

---

## 1. The `[DEBUG-<id>]` tag

Every injected line carries a tag in this exact form:

```
[DEBUG-<4char-id>] <one-line purpose>
```

- `<4char-id>` — a 4-character random alphanumeric (e.g. `a3f9`, `k2pq`). Lowercase preferred. Generate with `Math.random().toString(36).slice(2, 6)` or pick one yourself.
- `<one-line purpose>` — a brief human-readable description: `log inbound payload`, `flow marker B`, `render counter`, etc.

### Per-language comment syntax

| Language / context | Format |
|---|---|
| JS / TS / Java / C / C++ / Go / Rust / PHP (modern) | `// [DEBUG-<id>] <purpose>` |
| Python / Ruby / shell / Perl / YAML | `# [DEBUG-<id>] <purpose>` |
| CSS / SCSS / LESS | `/* [DEBUG-<id>] <purpose> */` |
| HTML / Vue / Svelte / JSX template / Blade | `<!-- [DEBUG-<id>] <purpose> -->` |
| SQL | `-- [DEBUG-<id>] <purpose>` |
| Lua / Haskell | `-- [DEBUG-<id>] <purpose>` |

The string `[DEBUG-` is the canonical grep anchor for cleanup. Don't customize it.

### Examples

```javascript
console.log('[DEBUG-a3f9] order payload at /checkout in', req.body); // [DEBUG-a3f9] log inbound payload
```

```python
print(f'[DEBUG-k2pq] user state: {user.__dict__}')  # [DEBUG-k2pq] state snapshot
```

```php
error_log('[DEBUG-x7m1] handler entry: ' . wp_json_encode($args)); # [DEBUG-x7m1] handler entry
```

```css
/* [DEBUG-r4tt] temporary outline to visualize stacking */
.suspect-element { outline: 2px solid magenta; }
```

The tag appears **both** inside the printed string AND in a trailing comment, so logs are searchable in two places: in console output and in source files.

---

## 2. One ID per investigation

All probes generated during a single hypothesis-testing round share the same `<id>`. If H1 needs 5 logs across 3 files, they all carry `[DEBUG-a3f9]`. When you cleanup, a single grep finds and removes all of them.

If you start a new hypothesis (Phase 5 → loop back to Phase 3 with H2), use a **new** id. That way you can clean up the failed H1 probes independently if you want to keep H2's around longer.

---

## 3. The debug ledger

Maintain a running ledger in the conversation as you inject probes. It lists every line you added, so nothing is forgotten in cleanup:

```markdown
## Debug ledger — investigation [DEBUG-a3f9]

| File | Line | Purpose |
|---|---|---|
| src/api/checkout.js | 42 | log inbound payload |
| src/api/checkout.js | 58 | log validation result |
| src/services/orders.js | 17 | log order.create input |
| src/services/orders.js | 26 | log order.create return value |
```

When cleanup is done, mark the ledger CLOSED:

```markdown
## Debug ledger — investigation [DEBUG-a3f9] — CLOSED (4 lines removed, grep verified clean)
```

---

## 4. Probe rules

These keep probes safe to run in real codebases — even on the user's main branch.

- **Observe, never mutate.** A probe reads and prints. It never writes to DB, never makes extra network calls, never changes state, never re-orders flow. The closest a probe should get to mutation is a `let _x = original_x; defineProperty(...)` trap whose set handler restores the original behavior after logging.
- **No async side effects.** Don't `await` anything new inside a probe. A probe that itself takes time changes the timing of the very thing it measures.
- **Don't log secrets.** If a payload may contain tokens, passwords, or PII, log only the keys/shape: `Object.keys(body)` not `body`. When in doubt, ask the user before logging.
- **Bounded output.** If a value could be huge (full DB row dump, entire DOM), slice it: `body.slice(0, 500)`, `JSON.stringify(x).slice(0, 1000)`.
- **One probe per concern.** Don't combine state snapshot + flow marker + timing in one log line — interpreting the output later is harder. Separate concerns, separate lines.

---

## 5. Cleanup checklist (Phase 7)

Before declaring the bug fixed, run this checklist:

1. **Read the ledger.** Visit every file:line listed.
2. **Remove every tagged line.** Use Edit to delete; don't comment them out.
3. **Grep the project:**
   ```bash
   grep -rn "\[DEBUG-" <project-root> --include="*.{js,ts,jsx,tsx,py,php,rb,go,rs,java,css,scss,html,vue,svelte,sql}"
   ```
   This must return **zero matches**. If it returns hits, you missed some — remove them.
4. **For the curl-wrapper / fetch-wrapper / property-trap probes** that live only in DevTools console: they're discarded by page refresh, but tell the user to refresh anyway so it's clear nothing is lingering.
5. **For DB-query logging hooks** (Laravel `DB::listen`, Rails subscribers, `SAVEQUERIES`): revert the config change too.
6. **For `WP_DEBUG = true`** changes in wp-config.php: ask the user whether they want to keep debug logging enabled or revert it. Don't decide for them — some sites leave it on in dev.
7. **Close the ledger.** Update the ledger header to `CLOSED` with a count of removed lines.
8. **Tell the user explicitly:** "All debug instrumentation removed. `grep` shows zero matches."

If you can't physically run grep (e.g., remote environment), ask the user to run it and paste the output back. Don't skip the verification.

---

## 6. Recovery from interrupted sessions

If a session ends before cleanup (user closes the chat, context is lost), the next debugger session in the same project should start by running:

```bash
grep -rn "\[DEBUG-" .
```

If hits appear, they're leftovers from a prior interrupted investigation. Surface them to the user before starting new work:

> I found leftover debug instrumentation from a prior session: [list]. Want me to remove these first, or keep them while we work?

This makes the cleanup contract robust across context loss.
