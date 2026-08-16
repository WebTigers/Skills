---
name: ui-ux-pro-max-php
description: A dependency-free PHP port of the ui-ux-pro-max design-intelligence database (50+ UI styles, ~190 color palettes, 74 font pairings, UX/accessibility guidelines, chart guidance, and per-stack rules) searchable from one PHP script. Use when making any UI/UX design decision — visual style, color palette, typography, UX heuristics, chart choice, or framework-specific guidance — for any app, PHP/Tiger or otherwise; query it BEFORE choosing design tokens or defaulting to habit.
---

# UI/UX Pro Max (PHP)

A faithful PHP port of the `ui-ux-pro-max` Claude skill. Same CSV design database, same
BM25 search, same token-optimized output — but it runs on **PHP 8 with zero dependencies**
(no npm, no Python, no Composer). Built for Tiger/PHP environments, useful for any project.

## The discipline (read this first)

This database exists so design decisions are **grounded, not guessed**. Before you pick a
palette, a font pairing, a landing-page structure, or a component style:

1. **Search first.** Query the relevant domain before you reach for a default.
2. **A "no match" is real information.** The formatter says so explicitly — an empty result
   means the query did not hit the database, *not* that anything goes. Retry with broader or
   different keywords.
3. **If you still fall back to a generic default, say so out loud** — tell the user the
   database had no match and that you are using general design judgment instead.

## How to invoke

```bash
php scripts/search.php "<query>" [--domain <d>] [--stack <s>] [-n <N>|--max-results <N>] [--json] [--full]
```

Examples:

```bash
php scripts/search.php "fintech dashboard"                 # auto-detects the domain (→ product)
php scripts/search.php "calm trustworthy palette" --domain color
php scripts/search.php "form validation" --domain ux
php scripts/search.php "hero section" --domain landing
php scripts/search.php "buttons" --stack tiger-php         # Tiger/TigerZF guidance
php scripts/search.php "fintech dashboard" --json          # machine-readable JSON
php scripts/search.php "code smells" --domain react --full # do not truncate long fields
```

- **`--domain <d>`** forces a domain. Omit it and the tool auto-detects the best domain from
  the query (and reports the runner-up).
- **`--stack <s>`** searches a framework's guideline set instead of the design database.
- **`-n` / `--max-results`** caps results (1–20, default **3**).
- **`--json`** emits JSON; the default is compact markdown built for reading.
- **`--full`** stops long field values being truncated (~300 chars) — code samples and
  checklists are never truncated regardless.

## Domains (auto-detected, or pass `--domain`)

| Domain | Source CSV | Returns |
|---|---|---|
| `style` | styles.csv | UI style guides — keywords, best-for, mode support, effects, implementation checklist, design-system variables |
| `color` | colors.csv | Named palettes with full semantic token sets (primary/secondary/accent/bg/muted/destructive/ring + on-colors) |
| `chart` | charts.csv | Chart-type selection — when to use / avoid, accessibility grade, library recommendation |
| `landing` | landing.csv | Landing-page patterns — section order, CTA placement, color strategy, conversion notes |
| `product` | products.csv | Per-product-type recommendations — primary/secondary styles, landing pattern, palette focus |
| `ux` | ux-guidelines.csv | UX + accessibility rules — do/don't with good/bad code examples and severity |
| `typography` | typography.csv | Font pairings — heading/body fonts, mood, Google Fonts URL, CSS import, Tailwind config |
| `google-fonts` | google-fonts.csv | Google Fonts catalog — family, classification, axes, subsets, popularity |
| `icons` | icons.csv | Curated icon guidance — name, library, import code, semantic role, allowed contexts |
| `gsap` | motion.csv | Motion presets — intensity tier, trigger, easing, GSAP snippet, performance notes |
| `react` | react-performance.csv | React performance rules — do/don't, good/bad code, severity |
| `web` | app-interface.csv | Web app-interface guidance — do/don't, good/bad code, severity |

## Stacks (`--stack <s>`)

Framework-specific guideline sets (Category / Guideline / Do / Don't / good + bad code /
severity / docs URL):

`react`, `nextjs`, `vue`, `svelte`, `astro`, `swiftui`, `react-native`, `flutter`, `nuxtjs`,
`nuxt-ui`, `html-tailwind`, `shadcn`, `jetpack-compose`, `threejs`, `angular`, `laravel`,
`javafx`, `wpf`, `winui`, `avalonia`, `uno`, `uwp`, **`tiger-php`**.

`tiger-php` is this fork's addition: Tiger/TigerZF guidance — the vendored Bootstrap 5 PUMA
theme (zero build), skins as `--bs-*` CSS-variable overlays, server-rendered `.phtml` views
with **no inline script/style**, assets via `asset()` under `/_theme`, the `/api` message
pattern for data, `Tiger_Form`, menus from `menus.ini`, and opaque GrapesJS components.

## How ranking behaves

Search is **BM25** (k1=1.5, b=0.75) over each domain's searchable columns, with synonym
normalization and stopword filtering. Per-domain and stack score floors mean **weak/ambiguous
queries deliberately return nothing rather than a misleading match** — favor a specific,
multi-word query. Exact style names, landing-pattern names, and standalone API identifiers are
matched directly (bypassing the score floor).

## This fork's scope

- **Search + stack modes only.** The upstream `--design-system` generator (`design_system.py`)
  is **not ported in v1** — it is on the roadmap. Passing `--design-system` prints a notice.
- **Lean data.** Two large upstream catalogs are **omitted** from this fork to keep it small:
  `phosphor-icons-upstream.json` (~805 KB) and `google-font-licenses.json` (~423 KB). The
  curated `icons.csv` and `google-fonts.csv` remain, so the `icons` and `google-fonts` domains
  work; only the raw upstream icon/font-license catalogs are absent.

## Credit

Data and search design are ported from the MIT-licensed
[`nextlevelbuilder/ui-ux-pro-max-skill`](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill).
See `NOTICE` for full attribution and license.
