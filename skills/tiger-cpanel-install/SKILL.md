---
name: tiger-cpanel-install
description: Install Tiger on a shared cPanel account end to end — get HTTPS up first, drive the one-file web installer's wizard, and verify the site really works. Covers what an agent can do with the user's authenticated cPanel session versus what it must hand back. Use when asked to install, set up, or stand up Tiger on cPanel, shared hosting, or any host without shell access, and when an install has failed and needs diagnosing.
---

# Installing Tiger on shared cPanel

Get Tiger running on a shared cPanel account with no shell.

---

## Read this first: what needs which access

Steps are gated by **what session or privilege they require**, not by who you are. Work out which
column you are in before you start.

| Step needs | Agent driving the user's **logged-in cPanel browser session** | Headless / API-only client |
|---|---|---|
| **Nothing** — the installer wizard itself | does it | does it |
| **An authenticated cPanel session** — create the database, run AutoSSL | **can do it** — click through the UI | **hand back** with exact instructions |
| **WHM / reseller** — create the cPanel account | hand back (unless the user is a reseller and says otherwise) | hand back |

**If you do not have a cPanel session, say so early.** Being asked for a database halfway through an
install feels like a failure; being told at the start is just a step.

Two rules that hold in every column:

- **Never ask the user to paste a cPanel API token** to route around a missing session. That trades a
  thirty-second click for a security ask.
- **Never fake a result you could not achieve.** If you cannot run AutoSSL, the site is HTTP — say so
  plainly rather than describing it as secure.

---

## 1. Confirm the ground

- **PHP ≥ 8.1** — cPanel → *MultiPHP Manager*, per domain. Tiger's floor is `>=8.1`; the preflight
  refuses below it.
- **`mbstring`**, plus `zip` or `phar` for extraction.
- **The domain resolves publicly** — `dig +short A <domain>` must return the server's IP.
  Diagnose the two failures differently: `NXDOMAIN` means the domain is unregistered; `SERVFAIL` means
  it is registered but its nameservers are not answering for it.
- **You can upload a file** — File Manager or FTP. No shell; uploading is how the installer arrives.

## 2. Get HTTPS working **before** you install

Do this now, not at the end. **The wizard asks the user to choose an admin password, and on a plain
HTTP site that password crosses the network in the clear.** AutoSSL needs only DNS — not Tiger — so
there is no reason to install first.

**Prerequisite:** the domain must already resolve (§1). AutoSSL validates over the public hostname and
cannot issue otherwise — and when it fails afterwards, the user will reasonably conclude the *install*
is broken rather than DNS.

cPanel → **SSL/TLS Status** → tick the domain and `www` → **Run AutoSSL**. Give it a minute, reload,
and confirm a valid certificate.

- **With a cPanel session:** do it.
- **Without one:** hand the user those exact steps and wait. It is worth the pause.

If HTTPS genuinely cannot be had — DNS is not ready and the user wants to proceed anyway — that is
their call to make knowingly. Tell them the admin password will travel in the clear and that they
should change it once TLS is up.

## 3. The database

cPanel → *MySQL® Databases*: create a database, create a user with a strong password, then add the user
to the database with **ALL PRIVILEGES**. cPanel prefixes both, e.g. `cpuser_tiger`.

Collect the three values — database, user, password.

Why this step exists at all: a user-space PHP script is denied `CREATE DATABASE`/`CREATE USER`, because
cPanel owns provisioning and tracks its own prefixed names. The *installer* can never do this; an agent
with a cPanel session can, through the UI, exactly as a person would.

> **The password must not contain a double quote (`"`).** The installer writes `local.ini` as INI and
> refuses a password it cannot quote safely.

## 4. Place the installer

Take the current release — not a raw file from a branch:

```
https://github.com/WebTigers/TigerInstall/releases/latest
  → tiger-install.zip   (+ its .sha256)
```

Unzip and upload `tiger-install.php` into the domain's document root (`public_html`, or the addon
domain's docroot). Open `https://<domain>/tiger-install.php`.

## 5. Drive the wizard

| Screen | Does | Needs |
|---|---|---|
| **Requirements** | Preflight: PHP, extensions, writability | read the verdict |
| **Location** | Detects docroot, proposes an app dir **above** it | confirm the path |
| **Download** | Fetches the vendored ZIP, verifies its checksum, extracts | a version, or accept latest |
| **Database** | Tests the connection, writes `local.ini`, migrates | the three values from §3 |
| **Admin** | Creates the founding org + owner | email, password, org name |

**Retrying is safe and does not need a re-upload.** The installer only deletes itself *after* the owner
is created — every error path stops before that, so the file is still there. Provisioning has been
idempotent since installer **1.0.3**: a retry preserves existing secrets instead of regenerating them.
Fix what the error names and submit the step again.

**On a checksum failure, stop.** Since 1.0.3 a release shipping no `.sha256`, or one whose digest does
not match, aborts before extraction. Do not work around it. An official release missing its checksum is
a **release-side bug worth reporting**, not an obstacle to route past. (Manual upload exists for a ZIP
the human already has and trusts — it is a deliberate trust decision they make, never your workaround.)

---

## Verify — with content, not status codes

An HTTP 200 is not evidence the install worked. A shell with missing assets returns 200 too.

1. `https://<domain>/` returns a Tiger page with real content.
2. **A referenced CSS or JS asset actually loads.** Pull one `src`/`href` off the page and fetch it — a
   404 here means assets were never published, which is the most common "it looks broken" cause.
3. `https://<domain>/auth/login` accepts the owner credentials you just created.
4. The site is served over **HTTPS** (§2). If it is not, say so.

## After

- **Unstyled site?** Many shared hosts disable `symlink()`. Tiger detects that and **copies** published
  assets instead (core 1.5.2+), re-publishing on update. Check this before suspecting the theme.
- **Mail works with no SMTP setup** on a normal cPanel account — the local MTA is used. Configure SMTP
  only if the host blocks it.
- **`local.ini` sits above the docroot at `0600`**, holding the DB credentials plus the minted
  `tiger.crypto.key` and `tiger.security.pepper`. **Never regenerate those on an existing install** —
  encrypted settings become unreadable and pepper-dependent credentials stop verifying.

## When it goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| Preflight refuses on PHP | domain on an older PHP | *MultiPHP Manager* → ≥ 8.1 |
| "Tiger already appears to be installed" | a configured `local.ini` in the target | working as intended — pick another app dir, or clear the old install deliberately |
| Checksum missing or mismatched | release-side problem, or a truncated download | retry once; if a published release genuinely ships no `.sha256`, report it — do not bypass |
| A step errors | whatever the message names | fix it and resubmit — the installer is still there and retrying is safe |
| Site renders unstyled | assets neither linked nor copied | confirm core ≥ 1.5.2, re-publish assets from the admin |
| AutoSSL fails | domain does not resolve | fix DNS (§1), then re-run from the UI |
| Installer 404s **after the owner was created** | it self-deleted, as designed | the install finished — verify the site rather than re-uploading |

## Report back

Give the human something actionable, not a log:

- the site URL, and whether it is **HTTP or HTTPS**
- the admin URL and the account created
- anything you handed back and why — a step you could not reach, said plainly, costs far less than one
  discovered later

---

**Deeper reference:** the long-form runbook, kept current in the platform repo, is
[`CPANEL.md`](https://github.com/WebTigers/TigerCore/blob/main/CPANEL.md). Host requirements are in
[`INSTALL.md`](https://github.com/WebTigers/TigerCore/blob/main/INSTALL.md); the installer's design is in
[`INSTALLER.md`](https://github.com/WebTigers/TigerCore/blob/main/INSTALLER.md).
