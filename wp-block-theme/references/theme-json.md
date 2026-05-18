# theme.json Reference — WordPress Block Themes
*Default target: WordPress 7.0. Uses `"version": 3` (introduced in 6.6, still current in 7.0).*

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
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 3,
  "settings": {},
  "styles": {},
  "customTemplates": [],
  "templateParts": []
}
```

`"version": 3` is the current schema as of WordPress 6.6 and is still current in 7.0. There is no version 4. If you need to pin to a specific release schema, use `https://schemas.wp.org/wp/6.6/theme.json` instead of `trunk`.

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

- `defaultPalette: false` — hides the 20+ WordPress default colors from editors.
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

Define scoped block variations inside `theme.json` so the Site Editor can control their tokens natively. Each entry under `styles.blocks.{block}.variations.{name}` corresponds to a block style variation that a designer can apply by adding `is-style-{name}` to a block's class list.

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
          "border": { "blur": "10px", "radius": "12px" },
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
| Spacing        | `var(--wp--preset--spacing--{slug})`          | `var(--wp--preset--spacing--md)`          |
| Gradient       | `var(--wp--preset--gradient--{slug})`         | `var(--wp--preset--gradient--hero-gradient)` |

Always use these variables in your CSS rather than hardcoded values. This keeps the design system
consistent and allows future theme changes to propagate everywhere automatically.

---

## Child Theme Merging

The child theme's `theme.json` **merges with** (not replaces) the parent's `theme.json`.

- Array-type settings (e.g. `palette`, `fontFamilies`) are **overwritten** entirely by the child theme.
- **Token inheritance:** to preserve parent tokens without copying the entire array, set `"defaultPalette": true`. You can then reference parent colors via `var(--wp--preset--color--{slug})` while defining your own child palette.
- Scalar settings (e.g. `contentSize`, `custom`) are **overridden** by the child.

**Practical child theme.json example:**

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
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

## Data Views (dataviews.json)

In WP 7.0, you can define custom views for post types directly in `theme.json` or a separate `dataviews.json` file. This is essential for providing a professional, "SaaS-like" dashboard for clients.

```json
{
  "dataviews": {
    "types": {
      "product": {
        "defaultView": "grid",
        "fields": ["title", "price", "stock_status"],
        "perPage": 24
      }
    }
  }
}
```

Keep child `theme.json` minimal — only override what diverges from the parent.
