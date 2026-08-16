---
name: tiger-design
description: Design and build the visual layer of a Tiger (TigerZF) app the framework-native way — the PUMA/Bootstrap 5 zero-build theme, skins as CSS-variable overlays, reskinnable .phtml views, CMS blocks/partials/layouts, GrapesJS components, and theme modules. Use when styling or building any Tiger UI (a skin, a theme, a .phtml view, a CMS block or landing page) and to keep it reskinnable — no inline styles, no build toolchain.
---

# Designing for Tiger

Tiger's visual layer has one governing idea (ARCHITECTURE §9): **presentation is a *theme* (files,
resolved by path); content is *CMS data* (rows, portable); a *skin* is a CSS-only overlay.** Get this
split right and a redesign is a config flip, not a migration — and the whole UI stays reskinnable.

**Zero build toolchain.** PUMA (the default theme) is vendored Bootstrap 5 — no npm, no Sass, no
PostCSS. `bootstrap.min.css` + the bundle, dropped in. You theme at **runtime** with CSS variables, never
a compile step. Never introduce a frontend build; if a theme truly needs one, it stays quarantined in
that theme.

## Theme vs. skin — two axes, different weights

| | **Theme** | **Skin** |
|---|---|---|
| Is | a whole view layer (layouts, view scripts, structure) | a CSS-only override of the theme's variables |
| Weight | heavy, changes rarely | light, swappable, structurally inert |
| Per-tenant? | rare (white-label) | **yes — the tenant branding axis** |
| Example | `puma`, a `react` theme | `jaguar`, `bengal`, `<tenant>.css` |

## Add a skin = a CSS-variable overlay (the cheapest win)

A skin is `:root { --bs-* }` (plus component `--bs-btn-*` etc.) overrides in
`themes/<theme>/assets/skins/<name>.css` — **auto-discovered**, plus a catalog entry. No structure, no
rebuild; it hot-swaps live (per-user via a cookie, per-org via a `config` row). Recolor by overriding
Bootstrap's variables, not by writing component CSS.

```css
/* themes/puma/assets/skins/bengal.css */
:root {
  --bs-primary: #e67e22;
  --bs-primary-rgb: 230,126,34;
  --bs-body-font-family: system-ui, sans-serif;
  --bs-border-radius: .5rem;
}
```

Light/dark is Bootstrap-native via `data-bs-theme` (browser/light/dark toggle, no-flash resolution) —
define skin variables so they read well in both.

## The golden rule for a `.phtml` view: no inline `<script>` / `<style>`

**Never put inline `<script>` or `<style>` in a `.phtml`** (the one exception is CMS page *content*). JS
goes through `$this->asset('js/foo.js')` (cache-busted, root-relative `/_theme/...`); CSS belongs in a
skin. Inline style can't be reskinned and violates CSP hygiene. Build views with **semantic Bootstrap
utility classes only** — no bespoke CSS — so a skin restyles every screen at once. This is exactly what
keeps the admin template (`tiger-admin-screen` skill) consistent.

Use the shipped vanilla primitives instead of hand-rolling: `TigerButton.run` (button busy state),
`TigerDOM.notify/toast` (themed alerts), `TigerModal.confirm/prompt/alert` (never the browser's native
dialogs), `TigerDOM.expand/collapse/toggle` (reveals). They are the house style — match them.

## CMS composition: everything is a labeled partial

Tiger's CMS renders three primitives from one `page` store by `type`: **pages**, **layouts**, and
**partials**. Compose a page from reusable partials with `[partial name="cta"]`; themes reuse menus with
`<?= $this->menu('primary') ?>` or `[menu name="primary"]`. Bodies render as **HTML** or **Markdown**
(safe) or **PHTML** (trusted), with a `[shortcode]` registry modules extend.

## Theme-shipped pages & components (file-based, not DB)

A theme ships **pages** as `content/<slug>.phtml` (a body partial + a `<!-- tiger:page title="…"
layout="…" skin="…" -->` hint) and **GrapesJS blocks** as `components/<id>.phtml` (a `<!-- tiger:block
label="…" category="…" icon="…" -->` hint). The builder registers each component as a draggable block; a
theme's palette is just a folder of hinted partials. `Tiger_Theme` reads them from the active theme dir.

**GrapesJS rule — opaque widget components.** A complex widget (carousel, marquee, tabs) is a component
that **generates** its HTML and GrapesJS just **saves it verbatim** unless edited — never round-trip
complex markup through the builder's DOM model (it mangles it). The marquee component is the reference.

## Theme scope: `site` vs `content`

`theme.json` declares how much a theme takes over: **`site`** (default) wraps every public request in the
theme's layout (a full redesign); **`content`** styles only the theme's own shipped pages and leaves the
CMS home/menu/other pages on the base theme (a vendor demo theme). Pick `content` when a theme's
chrome/nav is built for *its* pages and shouldn't hijack the site.

## Menus: `configs/menus.ini` (base tier), CMS overrides (live tier)

A theme declares nav in `configs/menus.ini` (one `[section]` per menu key, `items.<k>.label` + `url` or
`page_key`, nestable via `children`). `Tiger_Menu` renders the DB menu first and falls back to the
theme's `.ini` when a key has no DB rows — so a theme's menus render out of the box, and the CMS Menus
admin lists them as editable (fork-on-first-edit).

## Assets

All asset URLs are **root-relative** (`/_theme/...`), never a hardcoded FQDN (clean staging→prod,
ALB/proxy-safe). `$this->asset('css/x.css')` appends `?v=<filemtime>` so a deploy's changed CSS/JS is
picked up with no manifest and no hard refresh.

## Rules

- Recolor via a **skin** (`--bs-*` overlay), never component CSS; zero rebuild.
- **No inline `<script>`/`<style>` in `.phtml`** (CMS content exempt) — JS → `asset()`, CSS → skin.
- **Semantic Bootstrap utilities only** in views — no bespoke CSS (keeps it reskinnable).
- Use `TigerButton`/`TigerDOM`/`TigerModal` — never a hand-rolled spinner, alert, or native `confirm()`.
- Complex GrapesJS widgets = **opaque generate-and-save** components, not round-tripped markup.
- Content is portable **CMS rows**; presentation is **theme files** — never store theme-specific markup
  as page content (the WordPress/Divi lock-in Tiger explicitly avoids).

## See also
`tiger-admin-screen` (the reskinnable screen template), `tiger-module` (where a theme/feature lives),
and — for generic design intelligence (palettes, font pairings, UX heuristics) across many app types —
the `ui-ux-pro-max-php` skill. In a Tiger repo, `THEMES.md` + `ARCHITECTURE.md` §9 are authoritative.
