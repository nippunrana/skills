# Loading GSAP for Core Web Vitals

Structuring and loading GSAP code so page-load metrics (LCP, CLS, INP) stay
green: what may animate above the fold, how to split animation code by fold,
when to load ScrollTrigger, and how to measure the result. Runtime frame-rate
guidance lives in `references/performance.md`; the ScrollTrigger API in
`references/scrolltrigger.md`; framework lifecycle and cleanup in
`references/react.md` and `references/frameworks.md`.

## Contents

- [How animations affect LCP, CLS, INP](#how-animations-affect-lcp-cls-inp)
- [Rules](#rules)
- [Three-tier structure](#three-tier-structure)
- [Tier A: CSS start state](#tier-a-css-start-state)
- [Tier B: hero chunk](#tier-b-hero-chunk)
- [Tier C: below-the-fold chunk](#tier-c-below-the-fold-chunk)
- [Framework mappings](#framework-mappings)
- [CLS, font, and text-reveal traps](#cls-font-and-text-reveal-traps)
- [CSS alternative for simple below-the-fold reveals](#css-alternative-for-simple-below-the-fold-reveals)
- [Measuring](#measuring)
- [Best practices](#best-practices)
- [Do Not](#do-not)

## How animations affect LCP, CLS, INP

**LCP (Largest Contentful Paint).** Chrome does not treat an element as an LCP
candidate while it is painted at `opacity: 0` (Chrome 86+), while it has
`visibility: hidden` (never painted), or while its text is fully transparent
(Chrome 130+). LCP is recorded when the element first paints visibly. An
entrance animation that starts from hidden therefore delays LCP by the time
until the animation **starts**: script download, parse, execution, plus any
`delay`. The animation's duration barely matters; the wait for JavaScript does.
Documented cases: a hero `h1` at `opacity: 0` pushed LCP from under 2 s to
5.3 s; a product image hidden by a reveal attribute painted 6 s after it had
finished downloading.

Transform-only entrances (`y`, `x`, `scale`, `rotation`) do not have this
problem: the element paints on the first frame and counts immediately.

**CLS (Cumulative Layout Shift).** `transform` and `opacity` never cause layout
shift. `width`, `height`, `top`, `left`, `margin`, and wrappers inserted into
the flow do. ScrollTrigger's `pin: true` inserts a pin-spacer with bottom
padding at creation time. Only elements inside the viewport count toward CLS,
so **when** a pin is created decides whether that padding is a scored shift.

**INP (Interaction to Next Paint).** Scrolling is not an INP interaction, so
ScrollTrigger's per-frame work does not count. What hurts is one long
main-thread task at load: registering ScrollTrigger, creating dozens of
triggers, and running `ScrollTrigger.refresh()` while the user's first tap is
waiting.

**File sizes** (gsap 3.15.0, `dist/*.min.js`, gzip):

| File | gzip |
|---|---|
| `gsap.min.js` (core + CSSPlugin) | 28.3 KB |
| `ScrollTrigger.min.js` | 18.0 KB |
| `SplitText.min.js` | 3.7 KB |
| `ScrollSmoother.min.js` | 5.5 KB |
| `Flip.min.js` | 9.7 KB |

The hero never needs ScrollTrigger. Keeping it out of the first chunk removes
roughly 18 KB and all trigger setup work from the critical path.

## Rules

- **Never hide the LCP element before JavaScript runs.** No `opacity: 0`,
  `autoAlpha: 0`, `visibility: hidden`, or `color: transparent` on the hero
  heading or hero image in the initial CSS, and no `gsap.from()` with those
  values on it. This applies to the LCP element only. Other hero elements
  (subheadline, CTA, decoration) may still fade from 0.
- **Hero entrances animate transforms from a CSS-defined start state with
  `gsap.to()`.** `gsap.from()` is banned above the fold: it depends on
  JavaScript to apply the start state, so a late script makes already-visible
  content jump backwards and re-animate.
- **`opacity: 0.1` is the only allowed fade start on the LCP element,** and only
  when the design insists on a fade. State in the response that it satisfies
  the metric without changing what the user perceives.
- **The hero chunk imports gsap core only.** ScrollTrigger and every other
  plugin are banned from it.
- **Below-the-fold animation code is a separate dynamic `import()`** started
  after load: at idle with a timeout, on first scroll, or when a section
  approaches via IntersectionObserver with a `rootMargin`.
- **Pinned sections are created in the idle chunk right after load,** while the
  user is still at the top. Creating a pin only when it scrolls into view
  inserts the pin-spacer inside the viewport and is scored as CLS.
- **Every CSS pre-animation state gets a `<noscript>` reset.**
- **`content-visibility: auto` or `hidden` is banned on pages that use
  ScrollTrigger.** ScrollTrigger treats it like `display: none` and computes
  wrong start/end positions.
- **GSAP's official FOUC recipe (`visibility: hidden` in CSS, then `autoAlpha`)
  is for non-LCP elements only.** For the LCP element use the transform start
  state below.

## Three-tier structure

```
Tier A  CSS only     start state, optional CSS-only entrance, <noscript> reset
Tier B  hero.js      gsap core only, module script + modulepreload
Tier C  scroll.js    ScrollTrigger + below-the-fold sections, dynamic import() after load
```

Two files (hero vs below the fold) is the minimum split. Tier A is what makes
the split pay off: the hero is correct before any JavaScript arrives, so the
hero chunk can be late without hurting LCP.

## Tier A: CSS start state

```css
/* The hero is visible on first paint; only the transform is pre-set. */
.hero-title,
.hero-subtitle,
.hero-cta {
  transform: translateY(40px);
}

/* Non-LCP elements may also start faded. Never the title. */
.hero-subtitle,
.hero-cta {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .hero-title,
  .hero-subtitle,
  .hero-cta {
    transform: none;
    opacity: 1;
  }
}
```

```html
<noscript>
  <style>
    .hero-title, .hero-subtitle, .hero-cta { transform: none; opacity: 1; }
  </style>
</noscript>
```

Failure mode: `<noscript>` covers JavaScript being disabled, not the hero chunk
failing to load (blocked CDN, network error). In that case the hero stays
offset. When the hero needs no choreography, use a CSS-only entrance instead;
it has no script dependency and no LCP penalty:

```css
.hero-title {
  transform: translateY(0);
  transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
}

@starting-style {
  .hero-title {
    transform: translateY(40px);
  }
}
```

`@starting-style` (Baseline 2024) runs the transition on the element's first
render. Keep `opacity` out of the starting style for the LCP element.

## Tier B: hero chunk

```html
<link rel="modulepreload" href="/js/hero.js">
<script type="module" src="/js/hero.js"></script>
```

Module scripts never block parsing. `modulepreload` lets the browser fetch the
hero chunk (and a shared gsap chunk, if the bundler emits one) as early as
possible instead of discovering it later. The `import { gsap } from "gsap"`
form assumes a bundler (Vite, Next.js, Astro) or an import map. On a page with
no bundler, load `gsap.min.js` from a CDN with `defer` and use the global
`gsap`; `defer` scripts never block parsing either.

```javascript
// hero.js — gsap core only. No ScrollTrigger here.
import { gsap } from "gsap";

const mm = gsap.matchMedia();

mm.add("(prefers-reduced-motion: no-preference)", () => {
  gsap
    .timeline({ defaults: { duration: 0.7, ease: "power3.out" } })
    .to(".hero-title", { y: 0 })
    .to([".hero-subtitle", ".hero-cta"], { y: 0, autoAlpha: 1, stagger: 0.1 }, "-=0.5");
});
```

`gsap.to()` reads the CSS start state, so nothing needs `immediateRender`
handling and a late script only shortens the animation, never reverses it.
When reduced motion is on, the CSS media query has already removed the offset
and nothing runs.

## Tier C: below-the-fold chunk

Schedule the chunk after load. Calling `import()` twice returns the same
module, so the scroll listener and the idle callback cannot double-initialise.

```javascript
// end of hero.js (or a separate main.js)
const loadScrollAnimations = () => import("./scroll.js");

if ("requestIdleCallback" in window) {
  requestIdleCallback(loadScrollAnimations, { timeout: 2000 });
} else {
  setTimeout(loadScrollAnimations, 200);
}

// A fast scroller should not wait for idle.
window.addEventListener("scroll", loadScrollAnimations, { once: true, passive: true });
```

```css
/* Below the fold, a hidden start state is fine: these are not the LCP element. */
[data-reveal-item] {
  opacity: 0;
  transform: translateY(30px);
}

/* Pinned cards start off-screen. Use a length, not a percentage: GSAP reads
   the computed transform back as pixel x, so the tween below animates x. */
[data-pin] .card {
  transform: translateX(-100vw);
}
```

```javascript
// scroll.js — everything that needs ScrollTrigger
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

// One walk over the sections in DOM order, so a pinned section that sits
// between reveal sections is still created in page order.
document.querySelectorAll("[data-reveal], [data-pin]").forEach((section) => {
  if (section.hasAttribute("data-pin")) {
    // Pinned section: created here, right after load, never on viewport entry.
    gsap
      .timeline({
        scrollTrigger: {
          trigger: section,
          start: "top top",
          end: "+=200%",
          pin: true,
          scrub: 1
        }
      })
      .to(section.querySelectorAll(".card"), { x: 0, stagger: 0.2 });
    return;
  }

  gsap.to(section.querySelectorAll("[data-reveal-item]"), {
    y: 0,
    autoAlpha: 1,
    duration: 0.6,
    ease: "power2.out",
    stagger: 0.08,
    scrollTrigger: { trigger: section, start: "top 80%", once: true }
  });
});

ScrollTrigger.refresh();
```

Add the same `<noscript>` and reduced-motion resets for `[data-reveal-item]`
and `[data-pin] .card`. Do not split
creation into "all reveals, then the pin": that creates the pin out of page
order whenever it sits above a reveal section. If sections arrive from several
chunks in non-page order, give each ScrollTrigger a `refreshPriority` (higher
refreshes first) as described in `references/scrolltrigger.md`.

## Framework mappings

Every framework version keeps the same three tiers. The hero component imports
gsap core only; ScrollTrigger enters through a dynamic import after mount.

### React / Next.js (App Router)

Hero: a Client Component with `useGSAP`, gsap core only.

```jsx
"use client";
import { useRef } from "react";
import { gsap } from "gsap";
import { useGSAP } from "@gsap/react";

gsap.registerPlugin(useGSAP);

export function Hero() {
  const root = useRef(null);

  useGSAP(() => {
    gsap.to(".hero-title", { y: 0, duration: 0.7, ease: "power3.out" });
  }, { scope: root });

  return (
    <section ref={root} className="hero">
      <h1 className="hero-title">Ship faster with less code</h1>
    </section>
  );
}
```

Below the fold: keep the section HTML server-rendered and put only the
animation in a client wrapper that imports ScrollTrigger after mount. Anything
created after an `await` runs outside the hook's recording window, so create
the triggers inside `context.add()`; that records them in the `useGSAP` context
and reverts them on unmount.

```jsx
"use client";
import { useRef } from "react";
import { gsap } from "gsap";
import { useGSAP } from "@gsap/react";

const whenIdle = (fn) =>
  "requestIdleCallback" in window
    ? requestIdleCallback(fn, { timeout: 2000 })
    : setTimeout(fn, 200);

export function ScrollAnimations({ children }) {
  const root = useRef(null);

  useGSAP((context) => {
    let cancelled = false;

    whenIdle(async () => {
      const { ScrollTrigger } = await import("gsap/ScrollTrigger");
      if (cancelled) return;
      gsap.registerPlugin(ScrollTrigger);

      context.add(() => {
        root.current.querySelectorAll("[data-reveal]").forEach((section) => {
          gsap.to(section.querySelectorAll("[data-reveal-item]"), {
            y: 0,
            autoAlpha: 1,
            duration: 0.6,
            stagger: 0.08,
            scrollTrigger: { trigger: section, start: "top 80%", once: true }
          });
        });
        ScrollTrigger.refresh();
      });
    });

    return () => {
      cancelled = true;
    };
  }, { scope: root });

  return <div ref={root}>{children}</div>;
}
```

`next/dynamic(() => import("./ScrollSections"), { ssr: false })` is the
alternative when the section is not SEO content. It must be declared inside a
Client Component; Next.js throws when `ssr: false` is used in a Server
Component, and it skips server rendering of that component's HTML entirely.

### Vue / Nuxt

Hero: `onMounted` with gsap core, as in `references/frameworks.md`. Below the
fold: import ScrollTrigger at idle inside `onMounted`, create everything inside
one `gsap.context()`, revert it in `onUnmounted`.

```javascript
<script setup>
import { onMounted, onUnmounted, ref } from "vue";
import { gsap } from "gsap";

const root = ref(null);
let ctx;

onMounted(() => {
  const start = async () => {
    const { ScrollTrigger } = await import("gsap/ScrollTrigger");
    if (!root.value) return;
    gsap.registerPlugin(ScrollTrigger);
    ctx = gsap.context(() => {
      gsap.to("[data-reveal-item]", {
        y: 0,
        autoAlpha: 1,
        stagger: 0.08,
        scrollTrigger: { trigger: root.value, start: "top 80%", once: true }
      });
      ScrollTrigger.refresh();
    }, root.value);
  };
  "requestIdleCallback" in window
    ? requestIdleCallback(start, { timeout: 2000 })
    : setTimeout(start, 200);
});

onUnmounted(() => ctx?.revert());
</script>
```

In Nuxt, the `lazyLoadPlugin("ScrollTrigger")` composable from
`references/frameworks.md` replaces the raw `import()`.

### Svelte / SvelteKit

Same shape with `onMount`. The trigger is created after `onMount` returns, so
keep the context in a variable the cleanup can reach.

```javascript
<script>
  import { onMount } from "svelte";
  import { gsap } from "gsap";

  let root;
  let ctx;

  onMount(() => {
    const start = async () => {
      const { ScrollTrigger } = await import("gsap/ScrollTrigger");
      if (!root) return;
      gsap.registerPlugin(ScrollTrigger);
      ctx = gsap.context(() => {
        gsap.to("[data-reveal-item]", {
          y: 0,
          autoAlpha: 1,
          stagger: 0.08,
          scrollTrigger: { trigger: root, start: "top 80%", once: true }
        });
        ScrollTrigger.refresh();
      }, root);
    };
    "requestIdleCallback" in window
      ? requestIdleCallback(start, { timeout: 2000 })
      : setTimeout(start, 200);
    return () => ctx?.revert();
  });
</script>
```

### Astro

Astro ships static HTML, so Tier A is the default. A `<script>` inside
`Hero.astro` is bundled as a module and runs after parsing, which is Tier B.
Islands take Tier C:

```astro
<Hero />
<ScrollSections client:idle />
<Gallery client:visible={{ rootMargin: "400px" }} />
```

`client:idle` for sections that pin (created while the user is at the top);
`client:visible` with a `rootMargin` for simple reveals far down the page.

## CLS, font, and text-reveal traps

- **Pin timing.** A pin created while its section is in the viewport shifts
  everything below it. Create pins in the idle chunk right after load.
- **Media without dimensions.** Images and videos inside animated sections need
  `width` and `height` attributes. Lazy images without them change layout after
  ScrollTrigger has measured; if dimensions are impossible, call
  `ScrollTrigger.refresh()` from their `load` event.
- **SplitText and web fonts.** Splitting before fonts load produces wrong line
  breaks and a re-layout. Wait on `document.fonts.ready`, or set
  `autoSplit: true` and create the animation inside `onSplit()` so the re-split
  after font load re-creates it.
- **Text reveals on the hero heading.** Splitting the LCP heading is fine;
  hiding the pieces is not. Characters at `opacity: 0`, and lines pushed fully
  out of an `overflow: hidden` wrapper (`yPercent: 100` with `mask: "lines"`),
  leave no visible pixels, so the heading is ignored for LCP just like an
  element at `opacity: 0`. On the hero, animate `y` or `rotationX` on the
  pieces without hiding them; keep clipped and faded text reveals for
  below-the-fold headings.

## CSS alternative for simple below-the-fold reveals

For fade-and-slide reveals that need no sequencing, scroll-driven CSS
animations run on the compositor with no JavaScript at all. Elements using this
path are dropped from `scroll.js`, and their base styles stay visible so
unsupported browsers show static content.

```css
@supports (animation-timeline: view()) {
  [data-css-reveal] {
    animation: css-reveal linear both;
    animation-timeline: view();
    animation-range: entry 0% entry 40%;
  }

  @keyframes css-reveal {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
  }
}
```

Support as of 2026: Chrome and Edge 115+, Safari 26+, Firefox behind a flag,
roughly 82% of global traffic. Do not use it for the hero.

## Measuring

Use the attribution build of `web-vitals` and confirm two things: the reported
target is the hero element you intended, and its render delay is small. If a
smaller element is reported, the hero was ignored because it started hidden.

```javascript
import { onLCP } from "web-vitals/attribution";

onLCP(({ value, attribution }) => {
  console.log(
    "LCP", Math.round(value), "ms",
    "target:", attribution.target,
    "render delay:", Math.round(attribution.elementRenderDelay), "ms"
  );
});
```

Google ranks on field data from the Chrome User Experience Report at the 75th
percentile, not on a Lighthouse lab run. Test under CPU and network throttling:
a hidden hero looks fine on a fast machine and fails on a mid-range phone. The
Chrome DevTools Performance panel shows the same LCP breakdown per trace.

## Best practices

- ✅ Keep the LCP element visible on first paint; animate it with transforms
  from a CSS-defined start state using `gsap.to()`.
- ✅ Split animation code by fold: hero chunk with gsap core only, scroll chunk
  loaded by dynamic `import()` at idle or on first scroll.
- ✅ Create pinned ScrollTriggers right after load, in page order, then call
  `ScrollTrigger.refresh()` once.
- ✅ Add a `<noscript>` reset for every CSS pre-animation state.
- ✅ Prefer a CSS-only entrance (`@starting-style`) when the hero needs no
  choreography, and CSS scroll-driven animations for simple below-the-fold
  reveals.
- ✅ Verify with the `web-vitals` attribution build under throttling.

## Do Not

- ❌ Start the hero heading or hero image at `opacity: 0`, `autoAlpha: 0`,
  `visibility: hidden`, `color: transparent`, or fully clipped before JavaScript
  runs.
- ❌ Use `gsap.from()` for above-the-fold entrances.
- ❌ Import ScrollTrigger or any plugin in the hero chunk.
- ❌ Create a pinned ScrollTrigger lazily when its section enters the viewport.
- ❌ Use `content-visibility: auto` or `hidden` on pages that use ScrollTrigger.
- ❌ Create ScrollTriggers after an `await` without `context.add()` (React) or an
  explicit `gsap.context()` (Vue, Svelte); they will not be reverted on unmount.
- ❌ Gate a CSS-hidden start state behind `gsap.matchMedia()` with a conditions
  object whose only entry is `(prefers-reduced-motion: reduce)`; the handler
  never runs for default users and the elements stay hidden. Use the string form
  with `(prefers-reduced-motion: no-preference)` as in the snippets above.
- ❌ Judge the result by a Lighthouse score alone; check the LCP target and
  render delay with field-style attribution.
