---
name: tiger-admin-screen
description: Build a Tiger (TigerZF) admin or account screen from the standard template so it looks and behaves like the rest of the back office — extend the base controller, the five pieces (controller, ACL, settings-nav, Tiger_Form, view), and save through /api with TigerButton/TigerDOM. Use when adding a back-office management page, a settings screen, or a user-facing "My Account" screen to a Tiger app.
---

# Building a Tiger admin screen

Every admin surface in Tiger — core's and every module's — is built the **same way**, so the whole back
office looks like one product. Follow this template; don't invent a new shell. Semantic Bootstrap classes
only — **no bespoke CSS, no inline `<style>`** — so a reskin restyles every screen at once.

## The five pieces

### 1. Controller — extend `Tiger_Controller_Admin_Action`
The base sets `layout('admin')` for you; never call it yourself. The controller is **thin** — it renders;
every mutation is an `/api` call.

```php
class Docs_AdminController extends Tiger_Controller_Admin_Action
{
    public function init() { parent::init(); }   // the cascade hook; don't re-set the layout here

    public function settingsAction()
    {
        $form = new Docs_Form_Settings();
        $form->populate([ /* prefill from live config/model */ ]);
        $this->view->title = 'Docs Settings — Tiger Admin';
        $this->view->form  = $form;
    }
}
```
A full-screen action (a builder/canvas) calls `$this->_helper->layout()->disableLayout();` in *that* action.

### 2. ACL — admin+, deny-by-default (in the module's `configs/acl.ini`)
```ini
acl.resources.docs_admin_ctrl.resource   = "Docs_AdminController"
acl.resources.docs_settings_svc.resource = "Docs_Service_Settings"
acl.rules.docs_admin_ctrl.role       = "admin"
acl.rules.docs_admin_ctrl.resource   = "Docs_AdminController"
acl.rules.docs_admin_ctrl.permission = "allow"
; ...same block for the service
```

### 3. Settings-tree registration (for a settings page) — from the module Bootstrap
```php
protected function _initAdminSettings()
{
    Tiger_Admin_Settings::register([
        'key' => 'docs', 'label' => 'Docs', 'icon' => 'fa-book',
        'href' => '/docs/admin/settings', 'resource' => 'Docs_AdminController', 'order' => 60,
    ]);
}
```
It appears under Settings in the sidebar, ACL-filtered live, with no core edit.

### 4. Form — extend `Tiger_Form`, declare `elements()`; the view owns markup.

### 5. View — the standard skeleton
Header (`h3` title + `text-body-secondary` one-liner on the left, primary action(s) right) · a
`#…-feedback` mount · `.card`s for content · a footer script that saves through `/api`.

```php
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 mb-1">Docs</h1>
    <p class="text-body-secondary mb-0">Where your documentation site lives.</p>
  </div>
  <div class="text-nowrap">
    <button type="button" id="docs-settings-save" class="btn btn-primary">
      <i class="fa-solid fa-floppy-disk me-2"></i>Save
    </button>
  </div>
</div>
<div id="docs-settings-feedback"></div>
<form id="docs-settings-form" onsubmit="return false;" novalidate>
  <?= $this->form->getElement('_csrf') ?>
  <div class="card"><div class="card-header fw-semibold">Public route</div>
    <div class="card-body"><!-- fields --></div></div>
</form>
```

The save is **always** this shape (never a page-POST, never `<button type="submit">`):

```js
document.getElementById('docs-settings-save').addEventListener('click', function () {
  var form = document.getElementById('docs-settings-form');
  var fb   = document.getElementById('docs-settings-feedback');
  var fd = new URLSearchParams(new FormData(form));
  fd.set('module', 'docs'); fd.set('service', 'settings'); fd.set('method', 'save');
  TigerButton.run(this, function () {
    return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
      .then(function (r) { return r.json().catch(function () { return {}; }); });
  }).then(function (res) {
    if (res && res.result === 1) { TigerDOM.notify(fb, 'Settings saved.', { type: 'success' }); return; }
    if (res && res.form) { Object.keys(res.form).forEach(function (f) {
      var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
    }); }
    (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
  }).catch(function () { TigerDOM.notify(fb, 'Network error — please try again.', { type: 'error' }); });
});
```

The matching service (`Docs_Service_Settings`) validates the form and writes via the model (the config
tier for settings — live-override, no deploy). See the **`tiger-webservice`** skill.

## admin vs. account — two surfaces, one shell
`/admin` is the back office (manage the platform), gated admin+. `/account` is the user's own home
(manage MY stuff), open to any signed-in user. For an account screen, swap two things: extend
`Tiger_Controller_Account_Action` (not `_Admin_Action`), and contribute the menu item to
`Tiger_Account_Nav` (via `configs/navigation-account.ini` or `::register()`). Rule of thumb: an admin
screen manages *everyone*; an account screen manages *yourself*.

## House rules
- Extend the base controller — never set the admin layout by hand.
- Header = `h3` + `text-body-secondary` one-liner left, one `btn-primary` right; group content in `.card`s.
- A `#…-feedback` div driven by `TigerDOM.notify` — never `innerHTML = '<div class="alert">'`.
- Save through `/api` with `TigerButton.run`. · Semantic Bootstrap only — no bespoke CSS / inline `<style>`.
- Icons: Font Awesome, `me-2` before label; Save is `fa-floppy-disk`. · Strings are translate keys.

## See also
`tiger-webservice` (the save endpoint), `tiger-module` (the whole feature). In a Tiger repo, `ADMIN.md`
is the authoritative template.
