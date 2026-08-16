# WebTigers Skills

First-party [Agent Skills](https://www.anthropic.com/news/skills) for building on **Tiger** (TigerZF) —
the WebTigers platform. Each skill is a portable `SKILL.md` folder, so the *same* repo works in **Claude
Code**, the **Claude API**, and **TigerAgent**'s in-app Skills browser.

> **No npm. None wanted.** These are plain markdown skill folders plus a machine-readable
> `.claude-plugin/marketplace.json` index — discovered, browsed, and installed directly from GitHub.

## What's here

| Skill | What it teaches the agent |
|---|---|
| **tiger-module** | Scaffold a complete Tiger feature module — controller, `/api` service, form, ACL, views, migrations, i18n — the update-safe "extend, don't edit" way. |
| **tiger-webservice** | Write a Tiger `/api` web service correctly — the one-endpoint TIGER message pattern, validate → transaction, deny-by-default ACL, the standard response envelope. |
| **tiger-admin-screen** | Build an admin/account screen from the standard template so it matches the rest of the back office. |

## Install

**In TigerAgent** — open **Agent → Skills** in your Tiger admin, search `tiger`, review the `SKILL.md`,
and install. (Tiger browses this repo; it does not vouch for anything — review before you activate.)

**In Claude Code** — add this repo as a plugin marketplace, or drop a skill folder into your project's
`.claude/skills/`.

**Anywhere** — a skill is just a folder with a `SKILL.md`; copy the one you want.

## Layout

```
.claude-plugin/marketplace.json   # machine-readable index (name + description + source per skill)
skills/<name>/SKILL.md            # one skill: frontmatter (name, description) + instructions
```

Both the folder layout (`skills/<name>/SKILL.md`) and the manifest are standard, so any conforming
skill browser can consume this repo.

## Contributing a skill

See [AGENTS.md](AGENTS.md). In short: add `skills/<your-skill>/SKILL.md` with `name` + `description`
frontmatter, add a matching entry to `.claude-plugin/marketplace.json`, and open a PR. Skills are
**know-how** (instructions over an agent's existing tools), never new capability — keep them grounded in
Tiger's real conventions.

## License

[BSD-3-Clause](LICENSE) — Tiger's house license. Fork it, extend it, ship it — keep the notice. "Tiger"
and "WebTigers" are trademarks of WebTigers (see the platform's `TRADEMARKS.md`); the trademark is not
licensed by BSD-3. Individual skills that are **ports of upstream work** carry that upstream's license +
attribution in their own folder (e.g. an MIT `NOTICE`), which governs that skill.
