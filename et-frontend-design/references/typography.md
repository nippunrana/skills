# Typography: Choosing and Loading Fonts

This file is the single source of truth for font choice, font count, and weight budget. Read it
whenever a task picks, changes, or loads a font. Font loading mechanics (self-hosting, `next/font`,
fallback metrics) live in `references/design-system-foundations.md`.

---

## The Hard Rule: Two Font Families, Total

**Never ship a page that renders more than two font families.** Count every family the page
renders, whether downloaded or system-installed:

- Fonts the site theme or layout already loads globally — they count even if this page's design
  never references them.
- Icon fonts (Font Awesome, Material Icons) — they count. Use inline SVG icons instead.
- Monospace for code, data, or labels — it counts. If a tech brand needs mono, mono is one of the
  two families (e.g., Geist + Geist Mono, IBM Plex Sans + IBM Plex Mono).
- A "mixed serif and sans headline" uses the two families already chosen. It never adds a third.

One family is often enough. Hierarchy comes from size, weight, case, letter-spacing, and color —
not from adding typefaces.

**Why this rule exists:**
- **Performance.** The median web page already spends about 122–139 KB on fonts across about 4
  files (HTTP Archive Web Almanac 2025). Two families served as variable woff2 files cost about
  50–100 KB (Latin subset). Three or more families consume the whole median budget, add a font
  swap (and a layout-shift risk) per family, and cannot all be preloaded without starving the
  hero image.
- **Craft consensus.** Butterick ("Most documents can tolerate a second font. Few can tolerate a
  third."), Apple HIG ("Minimize the number of typefaces"), NN/g ("limit your design to 1-2
  fonts"), and Material 3 (exactly two typeface slots: brand and plain) all converge on 1–2.
- **No study supports more.** No experiment has measured how the *number* of typefaces changes
  perception. The nearest evidence (Tuch et al. 2012) shows visually complex pages lose appeal
  within 17 ms, and simple, conventional-looking pages win first impressions.

---

## Weight Budget

Every static weight and every italic is a separate file. Weights, not families, drive most of the
cost.

- **Body family:** 2 weights (regular + one bold). Italic only if the copy actually uses it.
- **Display family:** 1–2 weights.
- **3 or more weights in one family → use the variable font file.** A single variable file is
  larger than one static weight but smaller than about 3 static weights. (Google Fonts already
  returns the variable file when 2+ weights of a variable family are requested.)
- **Target:** total font transfer ≤ 100 KB (woff2, Latin subset) for a landing page.

---

## What the Research Says About Font Psychology

This is the evidence base. Apply it directly — do not repeat the research on every project.

### Robust — act on these

1. **Fit beats novelty.** The font's personality must match the brand and content.
   - Brands set in a font that suited the product were chosen about twice as often (Doyle &
     Bottomley 2004, replicated in a second experiment and a shop-shelf field test).
   - A font that clashed with the content lowered ratings of professionalism, trust,
     believability, and intent to use (Shaikh 2007; Fox, Shaikh & Chaparro 2007).
2. **People read consistent personalities into font classes** (Shaikh, Chaparro & Fox 2006,
   N ≈ 560; Brumberger 2003 found three clusters: elegance, directness, friendliness):

   | Font class | Perceived personality (research) |
   |---|---|
   | Serif | Stable, mature, formal |
   | Sans-serif | Neutral |
   | Display | Assertive, coarse |
   | Script / "fun" | Youthful, casual, creative |
   | Monospace | Dull, mechanical |

   **Craft mapping — designer convention, not tested research.** Use it to narrow the search,
   then let the fit check (rule 1) decide:

   | Direction | Common fit |
   |---|---|
   | Serif display | Finance, law, editorial, healthcare, premium |
   | Neo-grotesque sans | Product UI, SaaS, e-commerce body text |
   | Geometric sans | Consumer tech, startups, design-led brands |
   | Humanist or rounded sans | Health, education, community, consumer apps |
   | Heavy or condensed display | Headlines only — sport, events, campaigns |
   | Script | Short accents only (one word or a logo) — never body, CTA, or price |
   | Monospace | Developer tools, data products — as one of the two families |

3. **Serif vs sans makes no practical difference to reading speed** on modern screens (Arditi &
   Cho 2005). Choose between them on personality, never on a readability myth.
4. **Spacing helps readers; special fonts don't.** Extra letter and word spacing improves reading
   for dyslexic readers. "Dyslexia fonts" (Dyslexie, OpenDyslexic) perform no better than Arial
   (2026 meta-analysis, g ≈ −0.04).
5. **Hard-to-read fonts never help.** Disfluent fonts do not improve memory or reasoning
   (multiple failed replications; meta-analysis effect ≈ 0).

### Plausible — use as a tiebreaker only

- Instructions set in a hard-to-read font made a task seem harder and people less willing to do it
  (Song & Schwarz 2008; samples of about 10 per condition, no replication found). Consistent with
  rule 5: keep headlines, CTAs, prices, and form labels in the most legible face on the page.

### Debunked or weak — never cite these as a reason

- "Sans Forgetica improves memory" — failed to replicate in several studies.
- "Baskerville makes statements more believable" — a single statement, about 1.5% effect, not
  peer-reviewed.
- "Good typography boosts creativity" — about 20 people per group, a statistical error in the
  reported result.
- "Each reader's best font makes them read 35% faster" — no effect across the population.

---

## Font Selection Workflow

### Step 1 — Find the fonts the site already loads (Phase 1)

If the work lives inside an existing site or codebase, search it before choosing anything:

```bash
grep -rnE "@font-face|font-family|next/font|fonts\.googleapis|fonts\.bunny|use\.typekit" \
  --include='*.css' --include='*.scss' --include='*.html' --include='*.tsx' --include='*.jsx' \
  --include='*.ts' --include='*.js' --include='*.php' --include='*.liquid' .
```

Also check the platform's font config: WordPress `theme.json` (`settings.typography.fontFamilies`),
Tailwind config (`fontFamily`), Shopify `settings_schema.json` (`font_picker` settings). For a live
URL, open the page and list its loaded font files (browser DevTools → Network → Font).

Record: each family, its weights and styles, and whether it loads globally or per page.

### Step 2 — Ask: site-wide fonts or separate fonts (Phase 3)

Ask only when Step 1 found existing fonts. On greenfield work there is nothing to ask — state the
chosen pairing as an assumption in the strategy summary. Ask in plain markdown, with a
recommendation:

> **Typography:** Your site already uses **[Family A]** and **[Family B]**. Two options:
> - **Option A: Use the site fonts (recommended)** — zero extra download, and the page feels like
>   part of the same brand.
> - **Option B: Separate fonts for this page** — more distinct character, but the new fonts must
>   *replace* the site fonts on this page, not load on top of them. If the theme loads its fonts
>   globally, this page must stop loading them, or the page ends up with four fonts.
>
> Which fits better?

### Step 3 — Set the personality brief

From the strategy brief, pick 2–3 target traits (e.g., "trustworthy, modern, calm"). Map them to
one font class from the table above for the display role. The body role gets the most legible
neutral option that does not clash with the display font. If one family covers both roles, use one.

### Step 4 — Search the web for candidates

Search the web for current fonts matching the class and traits (e.g., "friendly humanist sans
variable font open source"). Check the foundry, Google Fonts, and Fontsource pages. Shortlist 2–3
candidates per role and verify for each:

- **Variable font available** and which axes (weight, optical size, width).
- **File size** of the Latin woff2 subset.
- **License** allows web self-hosting (OFL or a purchased web license).
- **Language coverage** for every language the site serves.
- **Superfamily siblings** (sans + serif + mono built on the same skeleton). Pairing two members of
  one superfamily guarantees the two faces look consistent together.

If web search is unavailable, choose from known, well-established families and tell the user the
candidates were not checked against current sources.

### Step 5 — Verify before delivering

After building, count what the page actually loads:

```bash
grep -rhoE "font-family:\s*['\"]?[^;,'\"]+" dist/ build/ .next/static/ 2>/dev/null | sort -u
```

Count the distinct family names in the delivered CSS, including system stacks such as
`ui-monospace` or `Georgia` — they count too. Cross-check the downloaded files in DevTools →
Network → Font. **If the page renders more than two families — including theme, icon, and system
fonts — remove fonts before delivering.** Also confirm
the total font transfer is within the weight-budget target.
