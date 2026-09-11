---
name: tiger-authoring
description: Connect to a Tiger site and build pages in it from plain English — the MCP/api handshake, then the exact tool sequence that turns "a page about X" into a published, reachable URL. Covers creating the page, fixing the navigation a fresh install inherits from its theme, adding images, and what to do when the agent surface is off, out of scope, or the site is half-installed. Use after installing Tiger, or when asked to add, write, or publish a page, post, or menu on an existing Tiger site.
---

# Authoring in Tiger

Turn a plain-English request into a page a person can actually reach.

Tiger exposes one surface two ways. **MCP** (`/mcp`) gives you a typed tool list reflected from the
live `/api` surface and filtered by your token's role. **`/api`** is that same surface over plain HTTP
for a client with no MCP transport. They are the same dispatcher underneath, so pick whichever your
client speaks — the tool names and arguments below are identical either way.

---

## 1. Connect

You need a **scoped token** and `/mcp` switched on. Both happen at install time if the user ticked
*"let the assistant that installed Tiger manage it"*, and the key was shown once on the finish screen.

**MCP client config** — the site's `/mcp/admin` screen emits both of these ready to paste:

```json
{ "mcpServers": { "tiger": {
  "command": "npx",
  "args": ["-y", "mcp-remote", "https://SITE/mcp", "--header", "Authorization: Bearer tgr_…"]
} } }
```

```json
{ "mcpServers": { "tiger": {
  "command": "php",
  "args": ["/path/to/mcp-bridge.php"],
  "env": { "TIGER_MCP_URL": "https://SITE/mcp", "TIGER_MCP_TOKEN": "tgr_…" }
} } }
```

Prefer **`mcp-remote`** if Node is available — it is the shorter path and needs nothing on the server.
Use the **PHP bridge** on a host with no Node, which includes most shared hosting.

Over plain HTTP instead: `POST /api` with `Authorization: Bearer tgr_…`. `GET /api/openapi` describes
the surface, when the site has discovery enabled.

**Confirm the connection before doing anything else.** Call `tools/list` (MCP) or fetch the OpenAPI
document. What comes back IS your permission set — Tiger reflects the tool list through the same ACL
that would refuse the call, so anything you cannot see, you cannot do. Do not guess at tool names.

---

## 2. Write the page

One call. `cms__page__save` takes the whole page:

| Argument | |
|---|---|
| `title` | required |
| `body` | the content |
| `format` | `html` · `markdown` · `phtml` · `builder` |
| `slug` | omitted → derived from the title |
| `page_key` | stable handle; omitted → derived from the slug. **Menus link by this**, so set it deliberately |
| `status` | `draft` · `published` · `archived` — a page is not live until `published` |
| `type` | `page` (default) · `layout` · `partial` |
| `layout_key` | the `page_key` of a `type=layout` row; omitted → the theme's default |
| `locale` | defaults to the site locale |
| `seo_title`, `meta_description`, `og_image_id` | optional |
| `page_id` | supply to UPDATE instead of create |

Pass `page_id` to edit. Omitting it creates a new page every time — that is the usual cause of
duplicates when a request is retried.

---

## 3. Make it reachable — do not skip this

**A page nobody can click is not published.** This is the single most common way an otherwise good
result disappoints, and it has a specific cause worth understanding.

**A fresh install's menu is not empty — it belongs to the theme.** Until something writes `menu` rows,
Tiger falls back live to the theme's `configs/menus.ini`. On the default theme that is *Tiger's own
marketing navigation* — "Why Tiger", "Solutions", `/features`, `/vibe` — pointing at routes the user's
site does not have. Leave it alone and their site ships with a nav where every link is wrong.

So:

1. **`cms__menu__importFromTheme`** — copies the theme's menu into editable rows. Idempotent: it skips
   a menu that already has items, so it is safe to call and safe to re-call.
2. **`cms__menu__delete`** — remove the inherited entries that do not apply to this site. Check each
   destination exists before keeping it.
3. **`cms__menu__save`** — add the item. Give it `menu_key` (which menu), `label`, and **`page_key`**
   pointing at the page you just made. Prefer `page_key` over `url`: it is a real binding that survives
   a slug change, and when both are set the page wins.
4. **`cms__menu__reorder`** — if position matters.

`cms__menu__revertToTheme` puts it back if you make a mess.

---

## 4. Anything else the request implied

| They asked for | Tool | |
|---|---|---|
| An image in the page | `media__media__upload` (you have the file) · **`tigerimage__image__generate`** (you need one made) | in scope |
| A blog post rather than a page | `blog__post__save` | in scope |
| A section with its own navigation | a `type=layout` page containing `[menu name="…"]`, then point pages at it with `layout_key` | in scope |
| Search | `search__search__*` | in scope |
| **A different theme or skin** | `system__settings__save` (`tiger.theme` / `tiger.skin`) | **NOT in the default token scope** |

The install-time token carries a curated starter set — **cms, blog, media, search, docs** — which
covers content but deliberately stops short of site configuration. If the request needs a theme or
skin change, say so and point the user at `/admin` or at `/mcp/admin` to widen the token. Do not try to
achieve it by editing content.

---

## 4b. Making an image that does not exist yet

If the site has **TigerImage** installed, you can generate pictures even if your own model cannot draw
— the module drives an image-capable provider on your behalf. This is what lets a text-only assistant
finish a page rather than hand back one with gaps where the images should be.

**Call `tigerimage__image__capability` FIRST, before you promise anything.** It answers without
generating, and an unavailable answer tells you *which* problem it is:

| `reason` | What it means | What to tell the user |
|---|---|---|
| *(available)* | a provider and key are configured | go ahead |
| `no_image_provider` | nothing installed can draw | "this site has no image provider configured" — the answer names which ones would work |
| `no_api_key` | the provider is set but has no usable key | a different fix: the key, not the provider |

Promising an image and discovering afterwards that none can be made is the failure worth designing
out. Ask first.

Then:

1. **`tigerimage__image__generate`** — `prompt`, plus optional `negative`, `size`, `n` (capped at 4
   per call), `seed`. Returns image ids and their parameters.
2. **`tigerimage__image__refine`** — `image_id` plus what you want changed. It inherits the parent's
   parameters and uses it as a reference, so "the same but warmer" is one call rather than a retype.
3. **`tigerimage__image__promote`** — `image_id`, optional `title`/`alt`. Returns a **`media_id`** you
   drop straight into the page. **Nothing is in the Media Library until you promote it** — generated
   images are drafts, and unpromoted ones are swept after the retention window.
4. **`tigerimage__image__discard`** — for the variants you did not pick. Leaving them costs the user
   money in storage and clutters their library later.

**You will not receive the image bytes.** A tool result is text in your context window, and base64 of
a single 1024px PNG is well over a megabyte of it. You get ids and metadata; a human looks at the
picture. Judge by the prompt you sent and the parameters echoed back, and if you need a human eye, say
so rather than guessing.

**Write real alt text.** Pass `alt` on promote. A generated image with no alt text is an accessibility
failure you introduced, and you are the one who knows what was asked for.

---

## 5. Verify like a person, not like an API

An HTTP 200 proves nothing. Finish by checking:

1. `https://SITE/<slug>` returns the page with its real content.
2. The site's **navigation contains a link to it**, and that link resolves.
3. No other nav item 404s — if you imported a theme menu, you own what you left in it.
4. It is served over **HTTPS**.

Report the **URL**, not the tool call. The user judges a page they can open.

---

## When it goes wrong

Each of these is a different answer. Tell the user which one it is.

| What you see | What it means | What to do |
|---|---|---|
| `/mcp` 404s | the server is off (`tiger.mcp.enabled`) | an admin enables it at `/mcp/admin` — you cannot |
| 401 / invalid token | the key is wrong, revoked, or never minted | mint a new one at `/mcp/admin`; keys are shown once |
| A tool you expect is absent from `tools/list` | the token's scope excludes it, or the ACL denies your role | widen the token at `/mcp/admin`. Absence IS the denial — do not retry, and do not look for another route to the same thing |
| `tools/call` returns a denial | same, at call time | as above |
| The site 500s, or `/admin` is missing | the install did not finish | re-run the installer; see the `tiger-cpanel-install` skill |
| The page saves but 404s | it is still `draft`, or the slug differs | set `status: published`; check the slug you were given back |
| The page loads but nothing links to it | §3 was skipped | import the menu and add the item |
| `tigerimage.error.no_image_provider` | nothing on this site can draw | say so; name the providers the answer lists. Do not retry |
| `tigerimage.error.no_api_key` | provider set, key missing or unreadable | an admin adds the key — a different fix from the above |
| `tigerimage.error.generation_failed` | the provider refused (often a safety filter) | the detail says why; change the prompt, do not retry it unchanged |

**Never fake a result.** If a page is a draft, do not call it live. If the menu is untouched, say so.

---

## Ground rules

- **Do not build a parallel path.** There is no authoring API to add — `/api` and MCP are the same
  surface, and every tool above already exists. If a step is awkward, that is worth reporting, not
  routing around.
- **Discovery is authorization.** The tool list is generated through the ACL that governs the call.
  Treat it as the complete truth about what you may do.
- **Least privilege is the default on purpose.** A token that cannot change the theme is working
  correctly, not broken.

## See also

- [`tiger-cpanel-install`](https://github.com/WebTigers/Skills/blob/main/skills/tiger-cpanel-install/SKILL.md)
  — getting Tiger onto a shared host in the first place.
- [`TIGERMCP.md`](https://github.com/WebTigers/TigerCore/blob/main/TIGERMCP.md) — the MCP contract,
  scopes, and the curated starter set.
- [`WEBSERVICES.md`](https://github.com/WebTigers/TigerCore/blob/main/WEBSERVICES.md) — the `/api`
  message pattern and response envelope.
- [`AUTHORING.md`](https://github.com/WebTigers/TigerCore/blob/main/AUTHORING.md) — layouts, partials
  and the CMS content model.
