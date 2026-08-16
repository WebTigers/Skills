---
name: tiger-webservice
description: Write a Tiger (TigerZF) /api web service the right way — the single-endpoint TIGER message pattern, validate-then-transaction, deny-by-default ACL, and the standard response envelope. Use whenever you add a data mutation or query to a Tiger/WebTigers app, or are tempted to build a REST-by-URL endpoint (don't — Tiger uses one /api message endpoint).
---

# Building a Tiger `/api` web service

Tiger does **not** use REST-by-URL. There is **one endpoint, `/api`**, and the routing metadata
(`module` + `service` + `method`) travels *inside* the POST body alongside the payload. The whole
message is handed to the named service method; the caller reads one JSON response object.

```js
await fetch('/api', {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
  body: new URLSearchParams({ module: 'billing', service: 'invoice', method: 'create', amount: '49.00' }),
});
// module=billing, service=invoice -> Billing_Service_Invoice ; method=create -> ::create($params)
```

## The canonical service flow — validate → transaction → envelope

A service extends `Tiger_Service_Service`. The message's `method` names the action, which receives
the **entire payload** as `$params`. **Always validate a form first, then wrap mutations in a
transaction.** Never emit business errors as bare strings or raw exceptions.

```php
class Billing_Service_Invoice extends Tiger_Service_Service
{
    /**
     * Create an invoice.
     * @param  array $params the /api message payload
     * @return void
     */
    public function create(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }   // defense-in-depth

        $form = new Billing_Form_Invoice();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }   // isValidPartial() for PATCH

        try {
            $id = $this->_transaction(function ($db) use ($params) {
                // ...inserts/updates via the query builder; throw to abort + roll back...
                return $newId;
            });
            $this->_success(['id' => $id], 'billing.invoice.created');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
```

Response helpers the base gives you:

| Helper | Effect |
|---|---|
| `_success($data, $msgKey, $redirect)` | `result=1`, payload, optional redirect + a success message |
| `_error($msgKey, $data)` | `result=0` + an error message |
| `_formErrors($form)` | `result=0` + `Zend_Form` field errors in `form` |

Message arguments are **translation keys** (`billing.invoice.created`), resolved in the caller's locale.

## The response contract (every service returns this shape)

```json
{ "result": 1, "data": {}, "redirect": null, "form": null,
  "messages": [ { "message": "Saved.", "class": "success", "field": null } ] }
```

`messages[].class` maps to a Bootstrap alert context (`success`/`error`/`alert`/`info`) so the client
renders feedback without inspecting content.

## Declaring the ACL is part of writing the call (deny-by-default)

`/api` is deny-by-default: the dispatcher checks `isAllowed($role, <ServiceClass>, <method>)` — the
**resource is the service class, the privilege is the method name**. A service with **no** allow rule is
refused outright. Add an entry in the module's `configs/acl.ini`:

```ini
; a rule with NO privilege allows ALL methods of the service to the role
acl.resources.billing_invoice_svc.resource = "Billing_Service_Invoice"
acl.rules.billing_invoice_svc.role         = "admin"
acl.rules.billing_invoice_svc.resource     = "Billing_Service_Invoice"
acl.rules.billing_invoice_svc.permission   = "allow"
```

When methods need different access (a public read on an otherwise-admin service), name the method as the
`privilege` on a scoped rule instead of a blanket one. The in-method `_isAdmin()` guard is
defense-in-depth — it does not replace the `acl.ini` rule.

## DataTables (server-side) is built in

For a grid, expose a `datatable(array $params)` action. `_dtParams()` normalizes the request
(`draw`/`start`/`length`/`search`/`order`); `_dtResponse($draw, $recordsTotal, $recordsFiltered, $data)`
emits `{draw, recordsTotal, recordsFiltered, data}` inside the envelope. Rows are **structured data
only, never HTML**, and each carries server-computed permission flags (`can_edit`, `can_delete`) the
client uses to gate controls. The client helper is `tigerDataTable('#id', {service, columns, order})`.

## Rules

- **One endpoint, message routing** — never build `GET /invoices/:id` REST routes.
- **Thin controllers, fat services** — never put business logic in a controller.
- **Validate then transact** — a `Tiger_Form` `isValid()`, then `_transaction()`; throw to roll back.
- **Query builder only** — `$db->select()->from(...)->where('col = ?', $v)`; never string-concatenated SQL.
- **ACL rule per service** — deny-by-default; declaring it is part of writing the call.
- **Translation keys, never hardcoded strings** — `<module>.area.type.name`.
- Kernel namespaces (`tiger`, `zend`, `core`, `default`, `library`, `application`) can **never** be a
  `module` — they're reserved and refused before any class is touched.

## See also
`tiger-module` (scaffold the whole feature), `tiger-admin-screen` (the back-office UI that calls this
service). In a Tiger repo, read `WEBSERVICES.md` + `AGENTS.md` for the authoritative contract.
