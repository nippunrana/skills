# Domain: Visual / UI Layout

Use this reference when the bug is a visible mismatch between expected and actual rendering — misaligned elements, CSS not applying, flexbox/grid layout broken, responsive breakage, z-index stacking, WordPress global styles overriding custom ones, hidden/clipped elements.

The probe mode for this domain is almost always a **browser-console snippet** — read-only, paste-and-run, copies a JSON diagnostic report to the clipboard. No source edits needed.

---

## 1. Classify the issue

Sub-category (drives which `computed` block to include in the snippet):

- `positioning` — wrong position, offset, overlap, z-index, transform
- `layout` — flexbox/grid not behaving, items not aligning, wrapping unexpectedly
- `spacing` — margin/padding/gap wrong, box-model issues
- `responsive` — mobile layout broken, media queries not triggering
- `cascade` — a rule exists but is being overridden (very common with WordPress global styles, Tailwind utilities, framework defaults)
- `visibility` — element hidden, clipped, zero-size, or behind something else

If you can't identify a specific element, use the **click-to-inspect** pattern (see bottom of this file).

---

## 2. Snippet template

Assemble a self-contained IIFE. Always include the **Core sections**. Add the **Targeted section** matching the sub-category. Keep the final snippet under ~100 lines.

### Core sections (always include)

**Environment detection** — auto-flags WordPress and captures viewport state:
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

**Element targeting** — replace `SELECTOR` with the actual selector from context. Never leave the placeholder:
```javascript
const sel = 'SELECTOR';
const el = document.querySelector(sel);
if (!el) { console.warn('[UI Debug] Not found:', sel); return; }
```

**Box model:**
```javascript
const rect = el.getBoundingClientRect();
const box = {
  offsetW: el.offsetWidth, offsetH: el.offsetHeight,
  scrollW: el.scrollWidth, scrollH: el.scrollHeight,
  rect: { top: +rect.top.toFixed(1), right: +rect.right.toFixed(1), bottom: +rect.bottom.toFixed(1), left: +rect.left.toFixed(1), w: +rect.width.toFixed(1), h: +rect.height.toFixed(1) },
  inViewport: rect.width > 0 && rect.height > 0 && rect.top < window.innerHeight && rect.bottom > 0,
};
```

**DOM ancestry** (up to 5 levels — layout context almost always lives in a parent):
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

**CSS cascade** — which rules are actually matching this element and from where:
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

**Report + auto-copy:**
```javascript
const cs = getComputedStyle(el);
// `computed` is filled by the targeted section below
const report = { env, selector: sel, box, computed, ancestry, cascade };
console.log('%c[UI Debug Report]', 'font-size:13px;font-weight:bold;color:#4ade80;background:#111;padding:4px 8px;border-radius:4px');
console.log(JSON.stringify(report, null, 2));
try { copy(JSON.stringify(report, null, 2)); console.log('%c✓ Copied to clipboard', 'color:#60a5fa'); } catch(e) {}
```

### Targeted sections by sub-category

**`positioning`:**
```javascript
const computed = {
  position: cs.position, top: cs.top, right: cs.right, bottom: cs.bottom, left: cs.left,
  inset: cs.inset, transform: cs.transform, zIndex: cs.zIndex,
  margin: [cs.marginTop, cs.marginRight, cs.marginBottom, cs.marginLeft],
  float: cs.float, clear: cs.clear,
};
```

**`layout`:**
```javascript
const computed = {
  display: cs.display, flexDirection: cs.flexDirection, alignItems: cs.alignItems,
  justifyContent: cs.justifyContent, flexWrap: cs.flexWrap, gap: cs.gap,
  alignSelf: cs.alignSelf, justifySelf: cs.justifySelf,
  flexGrow: cs.flexGrow, flexShrink: cs.flexShrink, flexBasis: cs.flexBasis,
  gridColumn: cs.gridColumn, gridRow: cs.gridRow, order: cs.order,
};
```

**`spacing`:**
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

**`responsive`** — include spacing + layout props, then add:
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

**`visibility`:**
```javascript
const computed = {
  display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
  overflow: cs.overflow, clip: cs.clip, clipPath: cs.clipPath,
  pointerEvents: cs.pointerEvents, zIndex: cs.zIndex, position: cs.position,
  width: cs.width, height: cs.height,
};
```

### Click-to-inspect pattern (when no selector is identifiable)

```javascript
console.log('%cClick the element you want to inspect...', 'color:#fbbf24;font-weight:bold');
document.addEventListener('click', function handler(e) {
  e.preventDefault(); e.stopPropagation();
  document.removeEventListener('click', handler, true);
  const el = e.target;
  // ... rest of inspection using el
}, { capture: true, once: true });
```

---

## 3. Signals to look for in the returned data

- **Cascade conflicts** — Is a higher-specificity rule in `cascade` overriding the expected one? Check its `source` — WordPress global stylesheets like `global-styles-inline-css` and `wp-block-library` are common offenders.
- **Layout parent** — Is a parent's `display`, `overflow`, or `flex`/`grid` property in `ancestry` the actual problem? The element you targeted may be a victim, not the cause.
- **Box model** — Is the element zero-sized, off-viewport (`rect`), or clipped by an ancestor's `overflow: hidden`?
- **WordPress overrides** — Check `wpStyles` against the `cascade` `source` field. WP's `global-styles-inline-css` has very high specificity and will quietly win.
- **Responsive misfire** — In `mq.active`, is the expected breakpoint missing? Or is the viewport reporting a different size than you expected?
- **Stacking** — `zIndex: auto` on a positioned ancestor traps children in that stacking context. Look at the whole ancestry, not just the element.

Once the signal is identified, find the rule and either raise its specificity, scope it more tightly, or remove the competing rule — at the source, not by piling `!important` on top.
