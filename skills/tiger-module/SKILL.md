---
name: tiger-module
description: Scaffold and build a complete Tiger (TigerZF) feature module — controller, /api service, Tiger_Form, ACL, views, config, migrations, i18n — the update-safe "extend, don't edit" way. Use when adding any new routed feature to a Tiger/WebTigers app, or when deciding whether something belongs in a module, the app library, or core.
---

# Building a Tiger module

A **module** is a self-contained feature with routes/controllers/ACL/views. It plugs into Tiger
**purely additively** — touching no framework file — and is auto-discovered by ZF1's module scan.

## First: does it already exist? And where does it belong?

1. **Check `CAPABILITIES.md`** (in a Tiger repo) before building — `grep -i <thing> CAPABILITIES.md`.
   Most substrate is already built; assume a capability exists until you've grepped and confirmed it
   doesn't.
2. **The four layers** — `Zend_*` (engine) · `Tiger_*` (platform) · `App_*` (app-shared, route-less) ·
   **modules** (features with routes/UI). A feature with controllers/routes/ACL/views is a **module**;
   shared plumbing with no routes is the **app library** (`library/App/`).

## The cardinal rule: extend, don't edit

`vendor/` is Tiger-owned and replaced by `composer update`. **Never edit a framework file to change app
behavior.** Extend instead: add a module, add config, subclass a `Tiger_*` base, add an app-library
class. `@api` = stable to build on; `@internal` = may change.

## Scaffold it

```bash
vendor/bin/tiger make:module <name>
```

This gives a live controller + `/api` service + ACL + views + config to build from.

## Module anatomy

```
application/modules/<name>/
  Bootstrap.php              ; extends Zend_Application_Module_Bootstrap; _init* hooks (nav, routes-override)
  controllers/               ; <Name>_IndexController, <Name>_AdminController (PSR-0 underscore — REQUIRED)
  services/                  ; <Name>_Service_*  (the /api surface — see the tiger-webservice skill)
  forms/                     ; <Name>_Form_*  (extend Tiger_Form)
  models/                    ; <Name>_Model_*  (extend Tiger_Model_Table)
  views/scripts/             ; .phtml (no inline <script>/<style> — see below)
  configs/acl.ini            ; deny-by-default access rules (auto-discovered)
  configs/routes.ini         ; OPTIONAL pretty aliases only (canonical path works free)
  migrations/                ; NNNN_name.php, additive-only
  languages/<lang>/<name>.php ; semantic, owner-prefixed i18n keys
```

## The pieces, briefly

- **Routing is free.** Every surface is reachable at its canonical `<module>/<controller>/<action>` path
  with **zero registration** (`/billing/invoice/view/id/42`). Only add a route for a *prettier* URL — and
  then in `configs/routes.ini` or a `Tiger_Routing_Overrides` declaration, **never** an `addRoute()` in a
  Bootstrap. No query params for navigation; use path-style params.
- **Services** are the `/api` surface — see the **`tiger-webservice`** skill (validate → transaction →
  ACL → envelope).
- **Forms** extend `Tiger_Form` and declare a declarative `elements()` array schema (not `.ini`); the base
  handles CSRF, POST, ViewHelper-only decorators (the view owns markup, forms are AJAX-submitted).
  Validators run at submit **and** on blur (convenience validation) — declare them once.
- **Models** extend `Tiger_Model_Table`: UUID v7 PK minted on insert, `created_at`/`updated_at` +
  `created_by`/`updated_by` stamps, soft-delete (`deleted`), finders exclude deleted via `activeSelect()`.
  Standard columns on every domain table: `status`, `deleted`, `created_by`, `updated_by`, timestamps.
  **JSON-shaped data → `LONGTEXT`, never the `JSON` type** (MariaDB's `JSON` rejects nesting ≥ 32 levels).
- **ACL** — every controller action and every service method is gated deny-by-default in `configs/acl.ini`
  (resource = class, privilege = action/method). A resource with no allow rule is denied.
- **i18n** — never hardcode user-facing strings. Semantic owner-prefixed keys (`<module>.area.type.name`)
  in `languages/<lang>/<module>.php`. In a **form** use `$this->_t('key')`; in a **view** there is **no
  `_t` helper** — use `Zend_Registry::get('Zend_Translate')->translate('key')`.
- **Config** — split by access pattern: eager, lean settings in the `config` tier (dot-keys, live-override,
  no deploy); per-user/on-demand state in the lazy `option` table. Never a `wp_options`-style grab-bag.
- **Migrations** are additive-only PHP files (`NNNN_name.php` returning `['up'=>[], 'down'=>[]]`), one
  logical DDL change each. Module migrations run on activate + via `bin/tiger migrate`.

## Client/server: the UI calls `/api`, it does not page-POST

Tiger apps are client/server. A controller renders the initial **shell** (SSR); from there the browser is
a client that exchanges JSON with `/api` over AJAX. Every insert/update/delete and every list/table fetch
is a service call — never a `<form>` page-POST, never server-rendered table rows.

## UI polish is the easy path (don't hand-roll it)

Use the shipped vanilla helpers instead of reinventing them: `TigerButton.run(btn, task)` (spinner +
disable + min-visible-time, failure-safe), `TigerDOM.notify/toast` (themed alerts), `TigerModal.confirm/
prompt/alert` (never the browser's native dialogs). **No inline `<script>`/`<style>` in `.phtml`** — JS
goes through `asset()`, CSS through a skin. (CMS page content is the only exception.)

## Anti-patterns (don't)

- Edit anything under `vendor/`. · Use `array()` (use `[]`). · Hardcode strings/roles/config.
- Put logic in controllers, or mutate without a form-validate + transaction.
- Build REST-by-URL endpoints (use the `/api` message pattern).
- `addRoute()` in a Bootstrap (routes are config). · Page-POST forms or server-render table rows.
- Inline `<script>`/`<style>` in a view. · Add columns to core `user`/`org` tables (extend via a module).

## See also
`tiger-webservice` (the `/api` service in depth), `tiger-admin-screen` (the back-office template). In a
Tiger repo, `AGENTS.md` + `ARCHITECTURE.md` are the authoritative source.
