# Domain: Code Logic, State & Async

Use this reference when the bug is wrong behavior in the code's logic: incorrect return value, conditional fails, state-management glitch, off-by-one, infinite loop, useEffect firing twice, race condition between two awaits, promise resolving in the wrong order, callback never invoked.

The probe mode here is usually **injected debug code** tagged per `instrumentation-protocol.md`, because the relevant data isn't visible in DevTools by default. Console snippets are only useful when the logic runs in the browser AND you can re-trigger it manually.

---

## 1. Sub-categories

- `state-snapshot` — you don't know what the variables actually contain at the suspect point
- `bisect-flow` — you don't know *which branch* of a function is executing (or whether it's executing at all)
- `async-order` — you don't know in what order two or more async operations are running
- `render-loop` — a React/Vue/Svelte component is re-rendering more than expected
- `value-mutation` — a value is being changed somewhere unexpected; you need to catch the writer

---

## 2. Probe patterns

### State snapshot (`state-snapshot`)

Log a snapshot of all relevant local state at the moment you suspect things go wrong. Use object shorthand so variable names are preserved.

```javascript
// [DEBUG-<id>] state snapshot at <function>:<reason>
console.log('[DEBUG-<id>]', { foo, bar, isValid, this_state: this.state });
```

```python
# [DEBUG-<id>] state snapshot at <function>:<reason>
import json; print('[DEBUG-<id>]', json.dumps({'foo': foo, 'bar': bar, 'is_valid': is_valid}, default=str))
```

```php
// [DEBUG-<id>] state snapshot at <function>:<reason>
error_log('[DEBUG-<id>] ' . json_encode(['foo' => $foo, 'bar' => $bar]));
```

### Print-trace bisection (`bisect-flow`)

When you don't know *where* the flow goes wrong, place markers at function boundaries and major branches. After one round, halve the suspect range. This is binary search applied to runtime control flow.

```javascript
console.log('[DEBUG-<id>] A: entered handleSubmit');        // [DEBUG-<id>] flow marker A
// ...
if (cond) {
  console.log('[DEBUG-<id>] B: cond=true branch');          // [DEBUG-<id>] flow marker B
} else {
  console.log('[DEBUG-<id>] C: cond=false branch');         // [DEBUG-<id>] flow marker C
}
// ...
console.log('[DEBUG-<id>] D: about to call API');           // [DEBUG-<id>] flow marker D
```

The marker that **fails to appear** in the output narrows the search. The marker right *before* the missing one is your new suspect zone.

### Async ordering (`async-order`)

Wrap awaits with timestamped before/after markers. If the order across runs is non-deterministic, you've found a race.

```javascript
console.log('[DEBUG-<id>] before fetchUser', performance.now());        // [DEBUG-<id>]
const u = await fetchUser();
console.log('[DEBUG-<id>] after fetchUser', performance.now(), u?.id);  // [DEBUG-<id>]

console.log('[DEBUG-<id>] before saveDraft', performance.now());        // [DEBUG-<id>]
await saveDraft();
console.log('[DEBUG-<id>] after saveDraft', performance.now());         // [DEBUG-<id>]
```

```python
# [DEBUG-<id>]
import time; print(f'[DEBUG-<id>] before fetch_user t={time.time():.4f}')
u = await fetch_user()
print(f'[DEBUG-<id>] after fetch_user t={time.time():.4f} id={u.id if u else None}')
```

### Render-loop counter (`render-loop`)

For React/Vue/Svelte components that re-render unexpectedly:

```javascript
// React
const renderCount = useRef(0);
renderCount.current++;
console.log(`[DEBUG-<id>] <ComponentName> render #${renderCount.current}`, { /* relevant props/state */ }); // [DEBUG-<id>]
```

```javascript
// Generic (any framework, top of component body)
// Bracket notation, not a dotted property: `<id>` is a placeholder and isn't
// valid inside a bare JS identifier, so this stays valid syntax even if you
// forget to substitute it before injecting.
window['__renderCount_<id>'] = (window['__renderCount_<id>'] || 0) + 1;
console.log(`[DEBUG-<id>] render #${window['__renderCount_<id>']}`); // [DEBUG-<id>]
```

If the count climbs faster than expected, log the props/state alongside it and find what's changing every render. Common culprits: inline object/array literals as props, missing memoization, parent re-renders, React 18 Strict Mode double-invoke (development only).

### Value-mutation trap (`value-mutation`)

When a value is being changed somewhere you can't find, replace the variable with a property that traps writes:

```javascript
// [DEBUG-<id>] trap writes to window.myConfig.token
let _token = window.myConfig.token;
Object.defineProperty(window.myConfig, 'token', {
  get: () => _token,
  set: (v) => { console.trace('[DEBUG-<id>] token written:', v); _token = v; },
  configurable: true,
});
```

```python
# [DEBUG-<id>] property trap on a class attribute
class _TrapDescriptor:
    def __init__(self, name): self.name = '_' + name
    def __get__(self, obj, _): return getattr(obj, self.name, None)
    def __set__(self, obj, v):
        import traceback; traceback.print_stack()
        print(f'[DEBUG-<id>] {self.name} written: {v!r}')
        setattr(obj, self.name, v)

# Attach to the CLASS, never to an instance — descriptors only intercept
# get/set when they live in the class __dict__. Assigning one to an
# instance attribute just overwrites the value and traps nothing:
SomeClass.token = _TrapDescriptor('token')    # correct
# some_obj.token = _TrapDescriptor('token')   # wrong — silently does nothing
```
Doesn't work unmodified on classes using `__slots__` (no per-instance `__dict__` for `setattr` to write into) — skip this probe for those, use `state-snapshot` instead.

`console.trace` (or Python's `traceback.print_stack()`) prints the call site of every write, so you find the rogue mutator.

---

## 3. When to choose what

| Situation | Tool |
|---|---|
| You can reproduce the bug at will and have an IDE attached | **Interactive debugger / breakpoint with watch expression** — fastest, no source edits, no cleanup |
| You can reproduce the bug but the codebase is server-side or hard to attach a debugger to | **Injected logs** with `[DEBUG-<id>]` tags |
| The bug is intermittent (happens 1 in 20 times) | **Injected logs** plus a counter, so the user can run it many times and you get aggregate data |
| The bug is reproducible by a specific test that fails | **Write a failing test first**, then debug inside the test runner |
| The bug is a regression (it worked before) and there's a command that reliably reproduces it | **`git log` / `git diff`** — read-only history and diff inspection; see git history inspection below |

If a project has Jest/Vitest/Pytest/PHPUnit configured, prefer making the bug reproduce in a test. That gives you a tight feedback loop and the test becomes the permanent regression case.

### Git history inspection (`git-history`)

When a bug is a confirmed regression, do not check out older commits or modify the git state (no staging, no commits, no stashing). Instead, narrow down the defect by reading the recent commit logs and diffs:

1. **Scan recent commit messages:**
   ```bash
   git log --oneline -15
   ```
2. **Inspect the exact diffs of recent commits:**
   To see what code actually changed in the last few commits:
   ```bash
   git log -p -n 5
   ```
3. **Check diffs for specific suspect files/directories:**
   If the symptom points to a specific component or file, view its history over the last 10 commits:
   ```bash
   git log -p -n 10 -- path/to/file.js
   # Or see the cumulative changes over the last N commits:
   git diff HEAD~10 -- path/to/file.js
   ```
4. **Inspect dependency changes:**
   Check if package versions or dependencies were recently modified:
   ```bash
   git diff HEAD~10 -- package.json package-lock.json composer.json composer.lock requirements.txt
   ```

By reading the diffs, you can spot the exact line that introduced the bug. This is faster and much safer than checking out older commits, which triggers dependency mismatches and state issues.

---

## 4. Signals to look for in the returned data

- **Missing log line** — execution never reached that branch. The gate is *upstream*. Look at the conditional that decides whether the missing branch runs.
- **Variable is `undefined`/`null`/empty** when it shouldn't be — trace where it was supposed to be set. Was the setter called? Did it run before or after the read?
- **Timestamps in unexpected order** — race condition. The fix is sequencing (await, lock, queue), not retry-on-failure.
- **Render count climbing** — find the prop/state that's different on each render. Inline `{}`/`[]` literals and inline arrow functions are the usual suspects.
- **Trap fires from unexpected stack frame** — that's your mutator. Fix at the writer, not by re-setting the value downstream.
- **Same probe produces different output across runs** — non-determinism. Likely an unawaited promise, a shared mutable cache, or environment-dependent input.

---

## 5. Fix discipline

- Fix the **defect that originated the bad value**, not the place where the bad value caused a visible failure.
- If the project has a test runner, write the regression test BEFORE fixing — confirm it fails, then make it pass.
- After fixing, run Phase 7 cleanup. Verify that searching the project for "[DEBUG-" returns zero matches using your native search tool.
