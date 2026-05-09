---
name: ui-debug-console
description: Generates a targeted, paste-ready browser console snippet to diagnose UI layout and styling problems. Use this skill whenever the user is stuck on a visual issue — misaligned elements, CSS not applying, flexbox/grid layout broken, responsive breakage, z-index stacking, WordPress global styles overriding custom ones, or any mismatch between expected and actual rendering. The skill reads the ongoing conversation to understand the stuck point, identifies the relevant element(s), classifies the issue type, and produces a single JavaScript snippet the user can paste into DevTools console and hit Enter. No extra input needed — the conversation is the context. Trigger this whenever a screenshot alone is not enough to debug a UI problem and you need real computed data from the live page.
---

# UI Debug Console Snippet Generator

You generate a single, targeted, paste-ready JavaScript snippet to collect diagnostic data from a live browser page. The user will paste it into their browser's DevTools console, hit Enter, and paste the output back to you so you can identify the root cause.

This skill always runs mid-conversation. The context of the problem is already in the chat — your job is to read it and generate the right snippet without asking redundant questions.

## Step 1: Read the Conversation and Extract Diagnostic Context

Before writing any code, silently identify:

1. **The stuck point** — What is visually wrong? What was expected vs. what is happening?
2. **The element(s)** — Extract CSS selectors, class names, IDs, or element descriptions from the conversation. If multiple files are open, check the active document for relevant class names.
3. **The issue category** — Classify into one or more of:
   - `positioning` — wrong position, offset, overlap, z-index, transform
   - `layout` — flexbox/grid not behaving, items not aligning, wrapping unexpectedly
   - `spacing` — margin/padding/gap wrong, box model issues
   - `responsive` — mobile layout broken, media queries not triggering
   - `cascade` — a rule exists but is being overridden (common with WordPress global styles)
   - `visibility` — element hidden, clipped, zero-size, or behind something

4. **The tech stack** — If the user mentioned WordPress, or file paths contain `wp-content`, include WP-specific probes. Otherwise the snippet auto-detects.

If you cannot determine a specific element, generate a **click-to-inspect** snippet (see Step 2).

## Step 2: Build the Snippet

Assemble the snippet as a self-contained IIFE. Structure it in clearly commented sections. Always include the **Core** sections. Then add the **Targeted** sections that match the classified issue category.

Keep the final snippet under ~100 lines. Focus on signal, not noise.

### Core Sections (always include)

**Environment Detection**
```javascript
const env = {
  isWordPress: !!(window.wp || document.querySelector('link[href*="wp-content"]') ||
    Array.from(document.body.classList).some(c => c.startsWith('wp-'))),
  viewport: { w: window.innerWidth, h: window.innerHeight },
  dpr: window.devicePixelRatio,
  url: location.href,
};
if (env.isWordPress) {
  env.wpBodyClasses = Array.from(document.body.classList);
  env.wpStyles = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
    .map(l => ({ id: l.id, file: l.href.split('?')[0].split('/').slice(-2).join('/') }));
}
```

**Element Targeting**
- Replace `SELECTOR` with the actual selector from context. Never leave a placeholder.
- If no selector is identifiable, use the click-to-inspect pattern (see below).
```javascript
const sel = 'SELECTOR';
const el = document.querySelector(sel);
if (!el) { console.warn('[UI Debug] Not found:', sel); return; }
```

**Box Model**
```javascript
const rect = el.getBoundingClientRect();
const box = {
  offsetW: el.offsetWidth, offsetH: el.offsetHeight,
  scrollW: el.scrollWidth, scrollH: el.scrollHeight,
  rect: { top: +rect.top.toFixed(1), right: +rect.right.toFixed(1), bottom: +rect.bottom.toFixed(1), left: +rect.left.toFixed(1), w: +rect.width.toFixed(1), h: +rect.height.toFixed(1) },
  inViewport: rect.width > 0 && rect.height > 0 && rect.top < window.innerHeight && rect.bottom > 0,
};
```

**DOM Ancestry** (up to 5 levels — layout context almost always lives in a parent)
```javascript
const ancestry = [];
let node = el.parentElement;
while (node && node !== document.body && ancestry.length < 5) {
  const s = getComputedStyle(node);
  ancestry.push({
    tag: node.tagName.toLowerCase(), id: node.id || null,
    classes: Array.from(node.classList).slice(0, 6).join(' ') || null,
    display: s.display, position: s.position,
    overflow: [s.overflow, s.overflowX, s.overflowY].join('/'),
    flex: s.display.includes('flex') ? { dir: s.flexDirection, align: s.alignItems, justify: s.justifyContent, wrap: s.flexWrap, gap: s.gap } : null,
    grid: s.display.includes('grid') ? { cols: s.gridTemplateColumns, rows: s.gridTemplateRows, gap: s.gap } : null,
  });
  node = node.parentElement;
}
```

**CSS Cascade** (which rules are actually matching this element and from where)
```javascript
const cascade = [];
for (const sheet of document.styleSheets) {
  let rules; try { rules = sheet.cssRules; } catch(e) { continue; }
  const src = (sheet.href || 'inline').split('/').slice(-2).join('/');
  for (const rule of rules) {
    try {
      if (rule.selectorText && el.matches(rule.selectorText))
        cascade.push({ selector: rule.selectorText, source: src, css: rule.style.cssText });
    } catch(e) {}
  }
}
```

**Report + Auto-copy**
```javascript
const report = { env, selector: sel, box, computed, ancestry, cascade };
console.log('%c[UI Debug Report]', 'font-size:13px;font-weight:bold;color:#4ade80;background:#111;padding:4px 8px;border-radius:4px');
console.log(JSON.stringify(report, null, 2));
try { copy(JSON.stringify(report, null, 2)); console.log('%c✓ Copied to clipboard', 'color:#60a5fa'); } catch(e) {}
```

---

### Targeted Sections by Issue Category

Add the relevant `computed` object based on the classified issue. Populate it with `getComputedStyle(el)` calls.

**`positioning`**
```javascript
const computed = {
  position: cs.position, top: cs.top, right: cs.right, bottom: cs.bottom, left: cs.left,
  inset: cs.inset, transform: cs.transform, zIndex: cs.zIndex,
  margin: [cs.marginTop, cs.marginRight, cs.marginBottom, cs.marginLeft],
  float: cs.float, clear: cs.clear,
};
```

**`layout`**
```javascript
const computed = {
  display: cs.display, flexDirection: cs.flexDirection, alignItems: cs.alignItems,
  justifyContent: cs.justifyContent, flexWrap: cs.flexWrap, gap: cs.gap,
  alignSelf: cs.alignSelf, justifySelf: cs.justifySelf,
  flexGrow: cs.flexGrow, flexShrink: cs.flexShrink, flexBasis: cs.flexBasis,
  gridColumn: cs.gridColumn, gridRow: cs.gridRow, order: cs.order,
};
```

**`spacing`**
```javascript
const computed = {
  boxSizing: cs.boxSizing,
  width: cs.width, minWidth: cs.minWidth, maxWidth: cs.maxWidth,
  height: cs.height, minHeight: cs.minHeight, maxHeight: cs.maxHeight,
  padding: [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft],
  margin: [cs.marginTop, cs.marginRight, cs.marginBottom, cs.marginLeft],
  gap: cs.gap,
};
```

**`responsive`** — include spacing + layout computed props, and add:
```javascript
const mq = {
  currentWidth: window.innerWidth,
  active: [375,480,640,768,1024,1280,1440].filter(bp => window.matchMedia(`(min-width:${bp}px)`).matches),
  isMobile: window.innerWidth < 768,
};
// add mq to report
```

**`cascade`** (specificity conflicts, especially WordPress) — after building `cascade`, add specificity scores:
```javascript
function specificity(s) {
  return (s.match(/#[\w-]+/g)||[]).length * 100 +
         (s.match(/\.[\w-]+|:[\w-]+|\[[\w-]+/g)||[]).length * 10 +
         (s.match(/^[a-z][\w-]*|\s[a-z][\w-]*/g)||[]).length;
}
cascade.forEach(r => r.score = specificity(r.selector));
cascade.sort((a,b) => b.score - a.score);
```

**`visibility`**
```javascript
const computed = {
  display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
  overflow: cs.overflow, clip: cs.clip, clipPath: cs.clipPath,
  pointerEvents: cs.pointerEvents, zIndex: cs.zIndex, position: cs.position,
  width: cs.width, height: cs.height,
};
```

---

### Click-to-Inspect Pattern (when no selector is identifiable from context)

Use this instead of a fixed selector when the user hasn't named a specific element:
```javascript
console.log('%cClick the element you want to inspect...', 'color:#fbbf24;font-weight:bold');
document.addEventListener('click', function handler(e) {
  e.preventDefault(); e.stopPropagation();
  document.removeEventListener('click', handler, true);
  const el = e.target;
  // ... rest of inspection using el
}, { capture: true, once: true });
```

## Step 3: Present the Snippet to the User

After the fenced code block, add a brief instruction section:

```
**What this collects:**
- Environment: [WordPress / plain HTML] (auto-detected)
- Element: `SELECTOR` — [describe what it is from context]
- Issue focus: [list the specific properties being checked]
- Auto-copies result to clipboard (Chrome DevTools)

**How to use:**
1. Open the page in your browser
2. Open DevTools → Console (F12 / Cmd+Option+J)
3. Paste the snippet and press Enter
4. Paste the console output (or clipboard contents) back here
```

Keep this section short — one sentence per bullet is enough.

## Step 4: Analyze the Output When Returned

When the user pastes the console output back:

1. Parse the JSON mentally and look for the root cause signal:
   - **Cascade conflicts**: Check `cascade` — is a higher-specificity rule overriding the expected one? What is its `source`?
   - **Layout parent**: Check `ancestry` — is a parent's `display`, `overflow`, or `flex`/`grid` property the actual problem?
   - **Box model**: Is the element zero-sized, off-viewport (`rect`), or clipped?
   - **WordPress overrides**: Check `wpStyles` — is a WordPress global stylesheet (e.g., `global-styles-inline-css`, `wp-block-library`) in the cascade with a higher specificity score?
   - **Responsive**: Check `active` breakpoints — is the expected media query not firing?

2. Identify the exact fix — file, selector, property, and value.
3. Confirm it matches what the user expected, and make the change.

## Rules

- **No side effects** — never mutate the DOM, storage, or cookies
- **Always wrap** `styleSheets` access in try/catch (cross-origin sheets throw)
- **Always include** `copy(...)` at the end — Chrome DevTools supports it natively
- **Never leave `SELECTOR` as a placeholder** — always resolve it from context
- **Multiple elements?** — `querySelectorAll` and loop, or target the primary one first
- **Keep it lean** — one IIFE, no imports, no dependencies, runs in any browser console
