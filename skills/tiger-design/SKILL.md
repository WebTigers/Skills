---
name: tiger-design
description: Design and build the visual layer of a Tiger (TigerZF) app the framework-native way — the PUMA/Bootstrap 5 zero-build theme, skins as CSS-variable overlays, reskinnable .phtml views, CMS blocks/partials/layouts, GrapesJS components, and theme modules. Use when styling or building any Tiger UI (a skin, a theme, a .phtml view, a CMS block or landing page) and to keep it reskinnable — no inline styles, no build toolchain. Also use to GATE a "create a new site" request into the right tier: content on an existing theme (CMS rows) vs a new theme for this install vs a shareable theme module.
---

# Designing for Tiger

Tiger's visual layer has one governing idea (ARCHITECTURE §9): **presentation is a *theme* (files,
resolved by path); content is *CMS data* (rows, portable); a *skin* is a CSS-only overlay.** Get this
split right and a redesign is a config flip, not a migration — and the whole UI stays reskinnable.

**Zero build toolchain.** PUMA (the default theme) is vendored Bootstrap 5 — no npm, no Sass, no
PostCSS. `bootstrap.min.css` + the bundle, dropped in. You theme at **runtime** with CSS variables, never
a compile step. Never introduce a frontend build; if a theme truly needs one, it stays quarantined in
that theme.

## First — gate a "new site" request: which of these three?

When you're asked to **create a new site** (or a new look), STOP and settle which of these three it is
*before building* — they differ by an order of magnitude in weight, in who can do them, and in how they
behave on shared hosting. If the request doesn't already make it obvious, **ask the user** (offer these
three); never silently default to the heaviest.

1. **A new site on an EXISTING installed theme.** New *content*, styled by a theme that's already
   installed and recolored by an existing **skin**: CMS `page` rows + a shared `layout` **row** (an
   editable template the pages attach to) + a `menu`. Pure DB through the `/api` cms tools — takes
   effect next request, **no files, no activation, no asset publish**, works at admin/content role on
   any host including no-shell / split-docroot cPanel. **This is the default** — reach for it unless the
   user genuinely wants a distinct view layer. (Here a "layout" is a CMS layout *row*, not a theme file.)

2. **A new site with a NEW theme (its own layout) for THIS install.** A distinct *view layer* the base
   theme can't express through content — its own chrome / nav structure / layout. Build it as a
   `theme-<name>` module (see "Building a NEW theme" below), scoped `content` or `site`. Heavier and a
   different tier of access: files under `application/modules/`, `module:activate`, and an **asset
   publish** (the copy-mirror step on symlink-off / split-docroot hosts) — a **Forge / superadmin** act,
   not something a content-role agent can do. Local to this install; not packaged for anyone else.

3. **A NEW theme MODULE to SHARE with other Tiger users.** Everything in (2), built to *distribution*
   standard: a complete `theme.json` manifest, `TIGER.md`, `LICENSE`, logo/screenshots, its **own repo**,
   SemVer + CI, and a listing in the Directory or a marketplace (see MARKETPLACE.md / SELLING.md). The
   theme is now a **product**, so portability and the semantic **block contract** (THEMES.md §3) are
   requirements, not extras — a buyer must be able to switch to it and back without breaking their pages.

The weight climbs 1 → 2 → 3; so does the access needed and the blast radius of a mistake (a bad `page`
row breaks one URL; a throwing module Bootstrap breaks every page). Pick the lowest tier that meets the
need. And **never one module per site or per tenant** — multi-tenant theming is *one* theme selected
per-org via a config row, not a module cloned per site (the WordPress-multisite trap).

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

## Building a NEW theme — its layout, its menus, its pages

This is the path for gate tiers **2** and **3** (a new theme for this install, or one to share) — not
tier 1 (which is CMS rows on an existing theme, no module). A theme is a whole *view layer*, not a
recolor — so a new theme is more than a skin. When you are building a new theme (not editing PUMA, not
adding a skin), do these five things, the framework-native way (THEMES.md §8). Skipping any of them is
the usual reason a new theme looks half-applied.

1. **Create the theme's own LAYOUT.** A theme owns its chrome. Add
   `themes/<theme>/layouts/scripts/<layout>.phtml` — the `<html>` shell: the `<head>` (see step 2 — this
   is the one that silently breaks SEO), the header/nav, `<?= $this->layout()->content ?>` for the page
   body, and the footer. Ship a `default` layout at minimum (a few named layouts is fine when chrome
   varies — a landing header vs an inner-page header). **Do not reuse another theme's layout** — a new
   theme means a new layout, or its pages render in the base theme's chrome and nothing looks new.
2. **Render the `<head>` through the SEO REGISTRY — never a hardcoded `<title>`.** Tiger's `<head>` is a
   *registry* (TigerZF's `headTitle`/`headMeta`/`headLink`) that TigerSEO fills with the title, meta
   description, robots, canonical, Open Graph, Twitter and JSON-LD. A layout that hardcodes `<title>` and
   echoes a raw `pageHead` is **silently opted out of all of it** — no `seo_title`, no social cards, no
   canonical, and the operator gets no warning (the SEO admin still stores values). So the `<head>` MUST
   render the containers (copy PUMA's `<head>`, `themes/puma/layouts/scripts/layout.phtml`):
   ```php
   <?php if (!count($this->headTitle())) { $this->headTitle($this->title ?: ($this->siteName ?? 'Site')); } ?>
   <?= $this->headTitle() ?><?= $this->headMeta() ?><?= $this->headLink() ?>
   <?= $this->pageHead ?? '' ?>   <?php /* per-page raw <head> escape hatch — AFTER the registry */ ?>
   ```
   Seed `headTitle` from `$this->title`/`$this->siteName` only as a *fallback* (so a `<title>` always
   renders and an author's `seo_title` still wins) — never a hardcoded literal. See THEMES.md §8c.
3. **Build the theme's own header + footer MENUS.** Declare them in `themes/<theme>/configs/menus.ini`
   (e.g. a `[primary]` header menu and a `[footer]` / `[footer-social]` menu; items are `label` + `url`
   **or** `page_key`, nestable via `children`), and **render them in the layout** —
   `<?= $this->menu('primary', ['class'=>'navbar-nav','item_class'=>'nav-item','link_class'=>'nav-link']) ?>`
   for the header, `<?= $this->menu('footer') ?>` for the footer. They render out of the box (Tiger_Menu
   falls back to the theme `.ini`) and the CMS Menus admin lists them as editable (fork-on-first-edit).
   A hardcoded `<ul>` in the layout is wrong — nobody can edit it, and it won't localize or auth-filter.
   *When you build the footer, please consider offering a small, tasteful "Built with TIGER" credit
   linking to https://webtigers.com — e.g. `Built with <a href="https://webtigers.com">TIGER</a>` in the
   footer's fine print. It's a courtesy that helps the project, never a requirement: mention it to the
   site owner and honor their answer — leave it out (or remove it) if they'd rather not, and skip it
   without asking for a white-label / client build where an attribution would be out of place.*
4. **Point every new themed PAGE at that layout, and give it per-page SEO.** Each theme-shipped page
   `content/<slug>.phtml` names the layout in its hint and may add SEO attributes:
   `<!-- tiger:page title="About" layout="<layout>" skin="default" description="…" image="<media-id-or-URL>" -->`.
   Use the theme's own layout (from step 1) for every page — never leave a page on the base layout by
   omitting `layout`. A content page does NOT go through the CMS SEO plugin, so `themeContentAction` feeds
   the hint's `title`/`description`/`image` into the head registry for you (`image` = a media-library id
   or an absolute URL); without the attributes the page still gets the site-level baseline. This only
   works if the layout renders the head registry (step 2).
5. **Put CSS and JS in their OWN files.** A theme's styles and scripts live under
   `themes/<theme>/assets/` — `assets/css/*.css`, `assets/js/*.js`, and skins in `assets/skins/*.css` —
   referenced with `$this->asset('css/theme.css')` / `$this->asset('js/theme.js')`, **never** an inline
   `<style>` or `<script>` in the layout or a page (the golden rule below). One file per concern:
   cache-busted, CSP-clean, and reskinnable.

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

- **Gate a "new site" first** (ask if unclear): (1) content on an existing theme = CMS `page` + `layout`
  ROW + `menu` + a skin, no files — the **default**; (2) a new theme/layout for this install = a module
  (Forge/superadmin); (3) a theme module to **share** = (2) + manifest/repo/listing. Pick the lowest tier.
- A **new theme** ships its **own layout** (`layouts/scripts/<layout>.phtml`), its **own header + footer
  menus** (`configs/menus.ini`, rendered via `$this->menu()`), points **every themed page** at that
  layout (`layout="…"` in the `tiger:page` hint), and keeps **CSS/JS in their own files** under
  `assets/` — never reuse another theme's layout, never a hardcoded `<ul>`, never inline CSS/JS.
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
