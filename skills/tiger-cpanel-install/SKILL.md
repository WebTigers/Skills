---
name: tiger-cpanel-install
description: Install Tiger on a shared cPanel account end to end — download the one-file web installer, drive its five-screen wizard, verify the site really works, and hand the three cPanel-only steps back to the human. Use when asked to install, set up, or stand up Tiger on cPanel, shared hosting, or any host without shell access, and when an install has failed and needs diagnosing.
---

# Installing Tiger on shared cPanel

Get Tiger running on a shared cPanel account with no shell. Most of it you can do. **Three things you
cannot**, and knowing which is which is the whole job.

---

## Read this before you start

Three steps are walled off from you by cPanel itself — not by convention, and not by anything you can
work around:

| Step | Who | Why it cannot be otherwise |
|---|---|---|
| Create the cPanel **account** | **the human** (WHM) | Reseller/root privilege. An account cannot create itself. |
| Create the **MySQL database + user + grant** | **the human** (cPanel → *MySQL® Databases*) | A user-space PHP script is denied `CREATE DATABASE`/`CREATE USER`. cPanel owns provisioning and prefixes names as `cpuser_name`. |
| Run **AutoSSL** | **the human** (cPanel → *SSL/TLS Status*) | Needs an authenticated cPanel session. There is no user-space path. |

**Tell the human about all three at the start**, not when you hit them. Being asked for a database
halfway through an install feels like a failure; being told up front is just a step.

Do **not** ask the user to paste a cPanel API token to route around this. You would be trading a
thirty-second click for a security ask, and it will be wrong more often than right.

---

## 1. Confirm the ground

- **PHP ≥ 8.1** — cPanel → *MultiPHP Manager*, per domain. Tiger's floor is `>=8.1` and the installer's
  preflight refuses below it.
- **`mbstring`**, plus `zip` or `phar` for extraction.
- **The domain resolves publicly** — `dig +short A <domain>` must return the server's IP.
  This does not block the install, but it *does* block AutoSSL (§5). Diagnose the two failures
  differently: `NXDOMAIN` means the domain is unregistered; `SERVFAIL` means it is registered but its
  nameservers are not answering for it.
- **You can upload a file** — File Manager or FTP. There is no shell; uploading is how the installer arrives.

## 2. Have the human create the database

cPanel → *MySQL® Databases*: create a database, create a user with a strong password, then add the user
to the database with **ALL PRIVILEGES**. cPanel prefixes both, e.g. `cpuser_tiger`.

Collect the three values — database, user, password.

> **The password must not contain a double quote (`"`).** The installer writes `local.ini` as INI and
> refuses a password it cannot quote safely. Choosing one without is faster than hitting the refusal.

## 3. Place the installer

Take the current release — not a raw file from a branch:

```
https://github.com/WebTigers/TigerInstall/releases/latest
  → tiger-install.zip   (+ its .sha256)
```

Unzip and upload `tiger-install.php` into the domain's document root (`public_html`, or the addon
domain's docroot). Then open `https://<domain>/tiger-install.php`.

> **It deletes itself on success.** One run per upload. A failed install that needs a retry means
> re-uploading the file — that is deliberate, so no `install.php` is left lying around.

## 4. Drive the wizard

| Screen | Does | Needs |
|---|---|---|
| **Requirements** | Preflight: PHP, extensions, writability | read the verdict |
| **Location** | Detects docroot, proposes an app dir **above** it | confirm the path |
| **Download** | Fetches the vendored ZIP, verifies its checksum, extracts | a version, or accept latest |
| **Database** | Tests the connection, writes `local.ini`, migrates | the three values from §2 |
| **Admin** | Creates the founding org + owner | email, password, org name |

Since installer **1.0.3** a release shipping no `.sha256` is refused outright, and a checksum mismatch
aborts before extraction — an unverifiable download is a stop, not a warning. Provisioning is also
idempotent: a retry preserves existing secrets instead of regenerating them.

## 5. AutoSSL — stop, and hand it over

**This one is not yours.** Check the prerequisite first, because AutoSSL validates over the public
hostname and cannot issue if the domain does not resolve:

```
dig +short A <domain>     # must return the server's IP
```

If that is empty, fix DNS first — otherwise AutoSSL fails and the user will reasonably conclude the
*install* is broken.

Then hand over, in words they can follow:

> Log in to cPanel → **SSL/TLS Status** → tick the domain and `www` → **Run AutoSSL**.
> Give it a minute, then reload; the domain should show a valid certificate.

Do **not** substitute a self-signed certificate, edit the SSL store, or describe the site as secure when
it is not. Say plainly: it works over HTTP and needs one click in the UI for HTTPS.

---

## Verify — with content, not status codes

An HTTP 200 is not evidence the install worked. A shell with missing assets returns 200 too.

1. `https://<domain>/` returns a Tiger page with real content.
2. **A referenced CSS or JS asset actually loads.** Pull one `src`/`href` off the page and fetch it — a
   404 here means assets were never published, which is the single most common "it looks broken" cause.
3. `https://<domain>/auth/login` accepts the owner credentials you just created.

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
| Download/checksum refused | release missing `.sha256`, or a partial fetch | retry; if a release genuinely ships none, download the ZIP and use the manual upload |
| Site renders unstyled | assets neither linked nor copied | confirm core ≥ 1.5.2, re-publish assets from the admin |
| AutoSSL fails | domain does not resolve | fix DNS (§5), then re-run from the UI |
| Installer 404s after a failure | it self-deleted on a partial success | re-upload and run again |

## Report back

Give the human something actionable, not a log:

- the site URL, and whether it is currently HTTP or HTTPS
- the admin URL and the account created
- **the AutoSSL instruction** if HTTPS is not yet active
- anything left undone, said plainly — an unfinished step reported honestly costs far less than one
  discovered later

---

**Deeper reference:** the full runbook, kept current in the platform repo, is
[`CPANEL.md`](https://github.com/WebTigers/TigerCore/blob/main/CPANEL.md). Host requirements are in
[`INSTALL.md`](https://github.com/WebTigers/TigerCore/blob/main/INSTALL.md); the installer's design and
the reasoning behind the three human-only steps are in
[`INSTALLER.md`](https://github.com/WebTigers/TigerCore/blob/main/INSTALLER.md).
