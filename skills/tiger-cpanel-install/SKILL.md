---
name: tiger-cpanel-install
description: Install Tiger on a shared cPanel account end to end — get HTTPS up first, drive the one-file web installer's wizard, and verify the site really works. Covers what an agent can do with the user's authenticated cPanel session versus what it must hand back. Also covers installing alongside a site already running on the account — creating the subdomain or second domain and installing into its document root. Use when asked to install, set up, or stand up Tiger on cPanel, shared hosting, or any host without shell access, to add Tiger to a subdomain or second domain beside an existing site, and when an install has failed and needs diagnosing.
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
| **An authenticated cPanel session** — create the database, add a domain or subdomain, run AutoSSL | **can do it** — click through the UI | **hand back** with exact instructions |
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

## 2. Decide where Tiger goes

Not every install owns the account. A user may be adding Tiger **beside a site that is already
running** — on a subdomain (`app.example.com`), or on a second domain parked in the same cPanel
account. Settle this before anything else: it decides which hostname gets the certificate (§3), which
database you create (§4), and which directory the installer is uploaded into (§5).

| They want | In cPanel | Docroot you install into |
|---|---|---|
| Tiger **is** the site | nothing to create | `public_html` |
| Tiger on a **subdomain** | *Domains* → **Create A New Domain** → `app.example.com` | cPanel proposes `public_html/app` |
| Tiger on **another domain** they own | same screen, enter the domain | `public_html/<domain>` |

**Creating the domain is a cPanel-session job** — the Domains UI, exactly as a person would. A
user-space PHP script can no more add a domain than it can create a database, so this sits in the same
column as §4 on the access table above.

Accept the document root cPanel proposes unless the user asks otherwise. It can be edited on that
screen, but the default is the well-trodden path and the one every other site on a typical server uses.

**DNS differs by case.** A subdomain of a domain already on the account needs nothing — it is served
from the existing zone. A *separate* domain must be registered with its nameservers already pointed at
this server: run the same `dig +short A` check from §1 against the new name before going further.

### What bites when you install beside an existing site

- **The new site has a second URL.** cPanel puts the docroot *inside* `public_html`, so
  `app.example.com` is also reachable at `example.com/app/`. That is a real path, not a redirect. Set
  Tiger's site URL to the hostname you intend, and do not advertise the other one.

- **The parent's `.htaccess` sits above your docroot.** If the existing site is WordPress — or Tiger —
  its `public_html/.htaccess` ends in a front-controller catch-all along the lines of
  `RewriteRule . /index.php [L]`, and your directory lives underneath it. The failure is nasty
  precisely because **the home page works**: `/` resolves to a real file. It is the *routes* that
  break. So verify a deep route, never just `/` — **Verify** below makes this a step.

- **Database names collide.** cPanel prefixes every database with the account name, so the obvious
  `cpuser_tiger` may already belong to the site that is running. Read the existing list first and pick
  something specific — `cpuser_tigerapp`. The same goes for the database *user*.

**Never modify the existing site to make room.** Do not edit its `.htaccess`, move its files, or
repoint its document root. If Tiger cannot go in cleanly beside it, say so and let the user decide —
breaking a working site to install a new one is never the trade to make on their behalf.

## 3. Get HTTPS working **before** you install

Do this now, not at the end. **The wizard asks the user to choose an admin password, and on a plain
HTTP site that password crosses the network in the clear.** AutoSSL needs only DNS — not Tiger — so
there is no reason to install first.

**Prerequisite:** the domain must already resolve (§1). AutoSSL validates over the public hostname and
cannot issue otherwise — and when it fails afterwards, the user will reasonably conclude the *install*
is broken rather than DNS.

cPanel → **SSL/TLS Status** → tick the domain and `www` → **Run AutoSSL**. Give it a minute, reload,
and confirm a valid certificate.

**Cert the hostname you are actually installing on.** If §2 had you create a subdomain or a second
domain, that new name is what the browser will show — ticking only the account's main domain leaves the
site you just built on a mismatched cert. A freshly created domain often appears in this list unticked.

**On a fresh account, tick the wildcard entry too.** If the list shows `*.<domain>`, include it in the
same run. A certificate covering only the bare domain and `www` leaves every other subdomain — the one
the user adds next week, and the service names cPanel creates itself — on an expired or mismatched cert,
which the browser reports as a security warning rather than as a missing certificate. Tick everything
the page lists; there is no cost to covering a name and a real cost to missing one.

If the wildcard is listed but AutoSSL will not issue for it, that is expected on some providers —
HTTP validation cannot prove control of a wildcard, so it needs DNS-based validation. Do not fight it:
the bare domain and `www` are what the install needs. Note it for the user and move on.

- **With a cPanel session:** do it.
- **Without one:** hand the user those exact steps and wait. It is worth the pause.

If HTTPS genuinely cannot be had — DNS is not ready and the user wants to proceed anyway — that is
their call to make knowingly. Tell them the admin password will travel in the clear and that they
should change it once TLS is up.

## 4. The database

cPanel → *MySQL® Databases*: create a database, create a user with a strong password, then add the user
to the database with **ALL PRIVILEGES**. cPanel prefixes both, e.g. `cpuser_tiger`.

Collect the three values — database, user, password.

Why this step exists at all: a user-space PHP script is denied `CREATE DATABASE`/`CREATE USER`, because
cPanel owns provisioning and tracks its own prefixed names. The *installer* can never do this; an agent
with a cPanel session can, through the UI, exactly as a person would.

> **The password must not contain a double quote (`"`).** The installer writes `local.ini` as INI and
> refuses a password it cannot quote safely.

## 5. Place the installer

Take the current release — not a raw file from a branch:

```
https://github.com/WebTigers/TigerInstall/releases/latest
  → tiger-install.zip   (+ its .sha256)
```

Unzip and upload `tiger-install.php` into **the document root you settled on in §2** — `public_html`
for a plain install, or the subdomain/second-domain docroot when installing beside an existing site.
Open `https://<domain>/tiger-install.php` on that hostname.

Getting this wrong is quiet: dropping the installer in `public_html` when Tiger was meant to live on
`app.example.com` installs it over the *existing* site's docroot.

## 6. Drive the wizard

| Screen | Does | Needs |
|---|---|---|
| **Requirements** | Preflight: PHP, extensions, writability | read the verdict |
| **Location** | Detects docroot, proposes an app dir **above** it | confirm the path |
| **Download** | Fetches the vendored ZIP, verifies its checksum, extracts | a version, or accept latest |
| **Database** | Tests the connection, writes `local.ini`, migrates | the three values from §4 |
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
4. The site is served over **HTTPS** (§3). If it is not, say so.
5. **Installed beside an existing site (§2)? Fetch a deep route** — `/auth/login` counts — and confirm
   the page is *Tiger's*, not the neighbouring site's. A parent `.htaccess` catch-all shows up exactly
   here and nowhere else: `/` is a real file and looks perfect, while every route falls through to the
   other site. Check the existing site still serves its own home page too.

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
