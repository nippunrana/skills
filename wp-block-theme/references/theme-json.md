# theme.json Reference — WordPress Block Themes
*Target: WordPress 7.0 and later. Uses `"version": 3` (introduced in 6.6, still current in 7.1). Keys that need 7.1 are in `references/core-7.1-apis.md`.*

## Table of Contents
1. [Root Structure](#root-structure)
2. [Color Palettes](#color-palettes)
3. [Gradients & Duotones](#gradients--duotones)
4. [Typography & Fluid Design](#typography--fluid-design)
5. [Spacing & Fluid Scale](#spacing--fluid-scale)
6. [Block-Specific Targeting](#block-specific-targeting)
7. [Custom CSS Injection](#custom-css-injection)
8. [CSS Variable Naming Convention](#css-variable-naming-convention)
9. [Child Theme Merging](#child-theme-merging)

---

## Root Structure

```json
{
  "$schema": "https://schemas.wp.org/wp/7.0/theme.json",
  "version": 3,
  "settings": {},
  "styles": {},
  "customTemplates": [],
  "templateParts": []
}
```

`"version": 3` is the current schema as of WordPress 6.6 and is still current in 7.1. There is no version 4. Pin `$schema` to the oldest release the theme supports (`wp/7.0`, or `wp/7.1` for a theme that requires 7.1) — `trunk` points to the development branch and may expose unreleased keys.

**Critical:** JSON must be perfectly valid. A single extra comma causes WordPress to silently
ignore the entire file. Always validate before saving.

---

## Color Palettes

```json
"settings": {
  "color": {
    "palette": [
      { "slug": "primary",    "color": "#1a1a2e", "name": "Primary Dark" },
      { "slug": "accent",     "color": "#e94560", "name": "Accent Red"   },
      { "slug": "neutral",    "color": "#f5f5f5", "name": "Neutral Light"},
      { "slug": "white",      "color": "#ffffff", "name": "White"        }
    ],
    "defaultPalette": false,
    "custom": false
  }
}
```

- `defaultPalette: false` — hides WordPress's own default colors from editors. This key is about core's palette only; it has nothing to do with a parent theme's colors.
- `custom: false` — disables the freeform color picker, enforcing the design system.
- Use logical semantic slugs (primary, accent, muted) over visual ones (red, blue).

---

## Gradients & Duotones

### Gradients

```json
"settings": {
  "color": {
    "gradients": [
      {
        "slug": "hero-gradient",
        "name": "Hero Gradient",
        "gradient": "linear-gradient(135deg, #1a1a2e 0%, #e94560 100%)"
      }
    ],
    "defaultGradients": false,
    "customGradient": false
  }
}
```

### Duotones (for images/covers)

```json
"settings": {
  "color": {
    "duotone": [
      {
        "slug": "brand-duotone",
        "name": "Brand Duotone",
        "colors": ["#1a1a2e", "#e94560"]
      }
    ]
  }
}
```

The first color maps to Shadows, the second to Highlights.
Supported blocks: `core/image`, `core/cover`, `core/site-logo`.

---

## Typography

### Font Sizes

Use standard slugs for maximum compatibility:

```json
"settings": {
  "typography": {
    "fontSizes": [
      { "slug": "small",   "size": "0.875rem", "name": "Small"   },
      { "slug": "medium",  "size": "1rem",     "name": "Medium"  },
      { "slug": "large",   "size": "1.5rem",   "name": "Large"   },
      { "slug": "xlarge",  "size": "2rem",     "name": "X-Large" },
      { "slug": "xxlarge", "size": "3rem",     "name": "XX-Large"}
    ],
    "customFontSize": false
  }
}
```

`customFontSize: false` prevents users from entering arbitrary pixel values.

### Local Font Integration

```json
"settings": {
  "typography": {
    "fontFamilies": [
      {
        "slug": "inter",
        "name": "Inter",
        "fontFamily": "Inter, sans-serif",
        "fontFace": [
          {
            "fontFamily": "Inter",
            "fontWeight": "400",
            "fontStyle": "normal",
            "src": ["file:./assets/fonts/Inter-Regular.woff2"]
          },
          {
            "fontFamily": "Inter",
            "fontWeight": "700",
            "fontStyle": "normal",
            "src": ["file:./assets/fonts/Inter-Bold.woff2"]
          }
        ]
      }
    ]
  }
}
```

Store font files in `assets/fonts/`. Paths are relative to `theme.json`.

### Typography UI Controls & Fluid Design

```json
"settings": {
  "typography": {
    "fluid": true,
    "fontStyle":       true,
    "fontWeight":      true,
    "letterSpacing":   true,
    "lineHeight":      true
  }
}
```

Enabling `fluid: true` allows WordPress to automatically calculate `clamp()` values for font sizes, ensuring perfect scaling across viewports without manual media queries.

---

## Spacing & Fluid Scale

### Spacing Scale

```json
"settings": {
  "spacing": {
    "spacingScale": {
      "steps": 0
    },
    "spacingSizes": [
      {
        "name": "Small",
        "slug": "small",
        "size": "clamp(0.625rem, 0.45rem + 0.88vw, 1.25rem)"
      },
      {
        "name": "Medium",
        "slug": "medium",
        "size": "clamp(1.25rem, 0.9rem + 1.75vw, 2.5rem)"
      },
      {
        "name": "Large",
        "slug": "large",
        "size": "clamp(2.5rem, 1.8rem + 3.51vw, 5rem)"
      }
    ],
    "units": ["px", "rem", "em", "vh", "vw"]
  }
}
```

To completely hide the spacing UI: `"spacingSizes": []`.

### Layout (Content Width)

```json
"settings": {
  "layout": {
    "contentSize": "800px",
    "wideSize": "1200px"
  }
}
```

- `contentSize` — default column width for constrained blocks.
- `wideSize` — maximum width for wide-aligned blocks (wide/full-width toggle).

### Root Padding Aware Alignments

```json
"settings": {
  "useRootPaddingAwareAlignments": true
}
```

When `true`, WordPress applies padding via a `.has-global-padding` class on group blocks instead
of on `body`. This lets "Full Width" blocks extend edge-to-edge while inner content still respects
the gutter. Essential for landing pages with full-bleed sections.

### Viewport Block Visibility (WP 7.0)

Block visibility needs **no** `theme.json` setting on 7.0 — it is available on every block that supports it, and `settings.blockVisibility.viewport` is not a real key. A block is hidden per viewport through its own `metadata.blockVisibility.viewport` attribute, an object such as `{"mobile": false}`. See `references/architecture.md → Viewport Block Visibility` for markup and rules.

On 7.1+, `settings.blockVisibility.allowEditing` and `settings.viewport` exist — see `references/core-7.1-apis.md`.

### Preset Dimension Values (WP 7.0)

Define reusable dimension presets in `settings.dimensions` so editors pick from a controlled list instead of entering arbitrary values. Slugs resolve to CSS custom properties with the pattern `var(--wp--preset--dimension--{slug})`.

```json
"settings": {
  "dimensions": {
    "aspectRatios": [
      { "slug": "square",     "name": "Square",     "ratio": "1" },
      { "slug": "landscape",  "name": "Landscape",  "ratio": "4/3" },
      { "slug": "widescreen", "name": "Widescreen", "ratio": "16/9" }
    ],
    "defaultAspectRatios": false,
    "minHeight": true,
    "dimensionSizes": [
      { "slug": "small",  "size": "320px", "name": "Small"  },
      { "slug": "medium", "size": "640px", "name": "Medium" },
      { "slug": "large",  "size": "960px", "name": "Large"  }
    ]
  }
}
```

- `defaultAspectRatios: false` hides WordPress-provided aspect ratio defaults and enforces only your design system ratios. These slugs appear in the `core/cover` block's "Aspect ratio" control.
- `dimensionSizes` (WP 7.0) — named width/height tokens that appear in the block inspector's dimension controls. Provides editors a constrained set of size options instead of a freeform input. Slugs resolve to `var(--wp--preset--dimension--small)` etc.
- **Block opt-in:** Width and height dimension controls are opt-in per block. In `block.json` (or `register_block_type` args), declare `"supports": {"dimensions": {"width": true, "height": true}}` to surface these controls in the inspector.

---

## Block-Specific Targeting

### In `settings` (controls editor UI for a block)

```json
"settings": {
  "blocks": {
    "core/post-date": {
      "typography": {
        "fontStyle": false,
        "fontWeight": false
      }
    }
  }
}
```

### In `styles` (applies CSS to a block type globally)

```json
"styles": {
  "blocks": {
    "core/heading": {
      "typography": {
        "fontFamily": "var(--wp--preset--font-family--inter)",
        "fontWeight": "700"
      },
      "color": {
        "text": "var(--wp--preset--color--primary)"
      }
    },
    "core/button": {
      "color": {
        "background": "var(--wp--preset--color--accent)",
        "text": "var(--wp--preset--color--white)"
      },
      "border": {
        "radius": "4px"
      }
    }
  }
}
```

### Button Pseudo-Element States (WP 7.0)

WP 7.0 exposes `:hover`, `:focus`, `:active`, and `:focus-visible` for the Button block directly in `theme.json`. For button states that apply site-wide, do not replicate these in a scoped `.css` file — the styles engine handles specificity and injects them into both the frontend and the editor iframe automatically.

```json
"styles": {
  "blocks": {
    "core/button": {
      "color": {
        "background": "var(--wp--preset--color--accent)",
        "text":       "var(--wp--preset--color--white)"
      },
      "border": { "radius": "4px" },
      ":hover": {
        "color": { "background": "var(--wp--preset--color--primary)" }
      },
      ":focus": {
        "outline": {
          "color":  "var(--wp--preset--color--accent)",
          "width":  "3px",
          "offset": "2px"
        }
      },
      ":active": {
        "color": { "background": "var(--wp--preset--color--primary)" }
      },
      ":focus-visible": {
        "outline": {
          "color":  "var(--wp--preset--color--accent)",
          "width":  "3px",
          "offset": "2px"
        }
      }
    }
  }
}
```

Rule: always define `:focus` and `:focus-visible` together with the same outline so keyboard and pointer users get identical visible focus styles. Never set `outline: none` without a visible replacement.

### Text Indent (WP 7.0)

`textIndent` is now a first-class typography property for Paragraph blocks. Set it globally, per-block, or per-variation.

```json
"styles": {
  "blocks": {
    "core/paragraph": {
      "typography": {
        "textIndent": "2em"
      }
    }
  }
}
```

To apply only to a specific variation (e.g. a "drop-cap" style), use `styles.blocks.core/paragraph.variations.{name}.typography.textIndent`.

**Two keys, two jobs.** `styles.blocks.core/paragraph.typography.textIndent` sets the indent *amount* (a CSS length such as `"2em"`). `settings.typography.textIndent` sets *which paragraphs* receive it:

| `settings.typography.textIndent` | CSS selector generated | Effect |
|---|---|---|
| `true` or `"subsequent"` | `.wp-block-paragraph + .wp-block-paragraph` | Indents only paragraphs that follow another paragraph. |
| `"all"` | `.wp-block-paragraph` | Indents every paragraph. |

```json
"settings": {
  "typography": {
    "textIndent": "all"
  }
}
```

Never put `"subsequent"` or `"all"` under `styles` — that key expects a length.

### Paragraph Column Layout

`typography.textColumns` (in `theme.json` since 6.3) maps to the CSS `column-count` property. In 7.0 the Paragraph block supports it, so editors also get a columns control.

```json
"styles": {
  "blocks": {
    "core/paragraph": {
      "typography": {
        "textColumns": "2"
      }
    }
  }
}
```

To apply it to specific paragraphs only, use a block style variation.

### Element-Level Targeting

```json
"styles": {
  "elements": {
    "link": {
      "color": { "text": "var(--wp--preset--color--accent)" },
      "typography": { "textDecoration": "none" },
      ":hover": {
        "typography": { "textDecoration": "underline" }
      }
    },
    "h1": { "typography": { "fontSize": "var(--wp--preset--font-size--xxlarge)" } },
    "h2": { "typography": { "fontSize": "var(--wp--preset--font-size--xlarge)" } }
  }
}
```

---

## Custom CSS Injection

### Global CSS (all pages)

```json
"styles": {
  "css": ".site-header { backdrop-filter: blur(10px); }"
}
```

### Block-scoped CSS

```json
"styles": {
  "blocks": {
    "core/group": {
      "css": "& .inner-content { max-width: 600px; margin: 0 auto; }"
    }
  }
}
```

Use `&` as the self-referencing selector (like Sass nesting). This scopes the CSS to the block's
root element.

---

## Section Styles (Variations)

Define scoped block variations inside `theme.json` so the Site Editor can control their tokens natively. Each entry under `styles.blocks.{block}.variations.{name}` styles the block style variation of that name — the same `{name}` the pattern-asset registry registers and the pattern's outermost block carries as `is-style-{name}`. Effects `theme.json` cannot express (such as `backdrop-filter`) go in the pattern's scoped `style.css`.

```json
"styles": {
  "blocks": {
    "core/group": {
      "variations": {
        "glass-card": {
          "color": {
            "background": "rgba(255, 255, 255, 0.1)",
            "text": "var(--wp--preset--color--white)"
          },
          "border": { "radius": "12px" },
          "spacing": { 
            "padding": { "top": "var(--wp--preset--spacing--large)", "bottom": "var(--wp--preset--spacing--large)" } 
          }
        },
        "hero-section": {
          "spacing": {
            "padding": { "top": "120px", "bottom": "120px" },
            "margin": { "top": "0", "bottom": "0" }
          }
        }
      }
    }
  }
}
```

---

## CSS Variable Naming Convention

WordPress auto-generates CSS custom properties for every preset:

| Preset type    | CSS variable pattern                          | Example                                   |
|----------------|-----------------------------------------------|-------------------------------------------|
| Color          | `var(--wp--preset--color--{slug})`            | `var(--wp--preset--color--accent)`        |
| Font family    | `var(--wp--preset--font-family--{slug})`      | `var(--wp--preset--font-family--inter)`   |
| Font size      | `var(--wp--preset--font-size--{slug})`        | `var(--wp--preset--font-size--large)`     |
| Spacing        | `var(--wp--preset--spacing--{slug})`          | `var(--wp--preset--spacing--medium)`          |
| Gradient       | `var(--wp--preset--gradient--{slug})`         | `var(--wp--preset--gradient--hero-gradient)` |

Always use these variables in your CSS rather than hardcoded values. This keeps the design system
consistent and allows future theme changes to propagate everywhere automatically.

---

## Child Theme Merging

The child theme's `theme.json` **merges with** (not replaces) the parent's `theme.json`.

- Array-type settings (e.g. `palette`, `fontFamilies`) are **overwritten** entirely by the child theme.
- **Keeping parent tokens:** there is no merge switch. A child that defines `palette` must repeat every parent entry it still needs, then add its own. A parent color the child omits no longer generates its `--wp--preset--color--{slug}` variable.
- Scalar settings (e.g. `contentSize`, `custom`) are **overridden** by the child.

**Practical child theme.json example:**

```json
{
  "$schema": "https://schemas.wp.org/wp/7.0/theme.json",
  "version": 3,
  "settings": {
    "appearanceTools": true,
    "color": {
      "custom": true,
      "link": true
    }
  },
  "customTemplates": [
    {
      "name": "my-landing-page",
      "title": "My Landing Page",
      "postTypes": ["page"]
    }
  ],
  "templateParts": []
}
```

---

Keep child `theme.json` minimal — only override what diverges from the parent.
