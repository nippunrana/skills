# Domain: Performance, Build & Tooling

Use this reference for two related-but-distinct bug families:

- **Performance** — slow renders, jank, memory leaks, hot loops, "the dashboard takes 6 seconds to open", "scrolling stutters"
- **Build / tooling / env** — Webpack/Vite errors, missing modules, env-var misconfig, PHP fatals, "it works on my machine but not in CI"

They share a reference because both need *measurement* before fixing — assumptions about what's slow or what's broken are almost always wrong.

---

## A. Performance probes

### `perf-timing` — measure a suspect block

Wrap the suspect code with `performance.mark` / `performance.measure`. Results show up in the Performance panel and as numbers in the console.

```javascript
// Browser or Node 16+
performance.mark('[DEBUG-<id>]-start');
// ... suspect code ...
performance.mark('[DEBUG-<id>]-end');
performance.measure('[DEBUG-<id>] suspect-block', '[DEBUG-<id>]-start', '[DEBUG-<id>]-end');
console.log(performance.getEntriesByName('[DEBUG-<id>] suspect-block').pop().duration.toFixed(2) + 'ms');
```

```python
# [DEBUG-<id>]
import time; t0 = time.perf_counter()
# ... suspect code ...
print(f'[DEBUG-<id>] suspect-block: {(time.perf_counter()-t0)*1000:.2f}ms')
```

### `perf-flamegraph` — find the slow function

Prefer the browser's built-in profiler over manual instrumentation when the slow path isn't obvious:

1. DevTools → Performance tab → Record
2. Trigger the slow action
3. Stop. Look at the flame graph for the widest yellow/red blocks. That's where time is being spent.

For Node: `node --prof app.js` then `node --prof-process isolate-*.log`. For Python: `python -m cProfile -o out.prof script.py` then view with snakeviz.

### `perf-render-count` — find unnecessary re-renders

See `references/code-logic.md` → "Render-loop counter". The same probe applies — log the render count and the props that changed each time.

For React DevTools users: open the **Profiler** tab and record. It shows which components rendered and how long each took. Highlight "components that rendered" to spot needless re-renders.

### `perf-memory` — find a leak

```javascript
// Snapshot heap size at intervals
const samples = [];
const id = setInterval(() => {
  if (performance.memory) {
    samples.push({ t: Date.now(), heap: (performance.memory.usedJSHeapSize/1048576).toFixed(1) + 'MB' });
    console.log('[DEBUG-<id>]', samples.at(-1));
  }
}, 2000);
// Stop with: clearInterval(id);
```

If `heap` climbs monotonically while the app sits idle → leak. To find what holds the references: DevTools → Memory → take a heap snapshot, do the action that should free things, take another snapshot, diff them, look for objects that should have been collected.

### Signals — performance

- **One function dominates the flame graph** → optimize that function. Don't speculatively memo everything.
- **Many small functions, no single offender** → either an N+1 problem (loop calling something expensive), or render thrash (look at render counts).
- **Layout/Style work shows up huge in Performance panel** → CSS-induced reflows. Look for synchronous `offsetWidth`/`getBoundingClientRect` reads in a loop after a write.
- **Memory grows without bound** → look for event listeners not removed, timers not cleared, closures capturing large objects, caches with no eviction.
- **CPU is fine but the UI feels slow** → main thread is starved by long tasks. Break work into chunks (`requestIdleCallback`, `setTimeout(_, 0)`, web workers).

---

## B. Build / tooling probes

### `build-first-error` — read the FIRST error, not the last

Build tool errors cascade — the visible final error is often a downstream consequence of an earlier failure. Scroll **up** in the build output to find the first error. Capture the full output:

```bash
npm run build 2>&1 | tee /tmp/build.log
# then in another terminal:
head -200 /tmp/build.log    # the FIRST errors, where the cause lives
```

Common cascade patterns:
- "Cannot find module X" later becomes 50 type errors that all reference X — fix X first.
- A Webpack loader error early in the run causes "unexpected token" later — the early loader failure is the cause.

### `build-env-diff` — env-var misconfig

When something works locally but breaks in CI/staging/prod, diff the env. Run this in each environment:

```bash
# Show only relevant vars (don't dump secrets)
node -e "console.log(Object.fromEntries(Object.entries(process.env).filter(([k]) => /^(NODE|VITE|NEXT|REACT|DATABASE|API)/.test(k)).map(([k,v]) => [k, v ? v.slice(0,8)+'…' : '<empty>'])))"
```

Or for shell tools:

```bash
env | grep -E '^(NODE|VITE|NEXT|REACT|DATABASE|API)_' | sed 's/=.\{8\}.*/=…/'
```

The diff between environments is your suspect list. Common offenders: `NODE_ENV` set to wrong value, API base URL pointing to localhost in prod, missing token, trailing whitespace in a copy-pasted secret.

### `build-version-check` — Node/PHP/Python version mismatch

```bash
node -v && npm -v && cat package.json | grep -E '"engines"|"packageManager"'
php -v && composer -V
python --version && pip --version
```

Mismatches with `engines` / `composer.json` / `pyproject.toml` requirements cause cryptic errors. A `node:18` vs `node:20` mismatch often produces "X is not a function" or "Cannot read properties of undefined" from a transitive dep that ships ESM-only or relies on a newer Node API.

### `build-lockfile-drift` — stale lockfile

```bash
# Did node_modules drift from the lockfile?
npm ls 2>&1 | grep -E 'invalid|missing|extraneous' | head -20
# Fresh install
rm -rf node_modules && npm ci
```

`npm ci` is stricter than `npm install` — it fails when lockfile and package.json disagree, which is what you want when chasing "works for them, not for me" bugs.

### `build-php-fatal` — WordPress / PHP

Enable WP debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);     // writes to wp-content/debug.log
define('WP_DEBUG_DISPLAY', false); // don't leak errors to page
```

Then tail the log while reproducing:

```bash
tail -f wp-content/debug.log
```

Look for the first `PHP Fatal error` or `PHP Stack trace` — everything after it is fallout.

### Signals — build / tooling

- **First error references a missing file/module** → check imports, paths, case sensitivity (Linux vs macOS), the actual file's existence.
- **Error references a path with `node_modules/.cache` or `.vite` or `.next`** → stale cache. `rm -rf` it and rebuild.
- **Error appears only in CI** → env-var or version difference. Diff them.
- **Different errors on different machines with same code** → lockfile drift or postinstall side-effect. Run `npm ci` from clean.
- **Cryptic error after working for weeks** → check git log for recent dep bumps; an upstream package may have shipped a breaking change in a patch release.

---

## C. Fix discipline

- For performance: measure → fix → measure again. If the second measurement doesn't show improvement, you fixed the wrong thing.
- For build/env: fix the actual misconfig (correct version, correct env var, correct lockfile). Don't pin around it or add fallback code paths.
- After fixing, run Phase 7 cleanup. Build/tooling probes rarely leave instrumentation in source, but perf probes do — `grep -rn "\[DEBUG-" .` to confirm clean.
