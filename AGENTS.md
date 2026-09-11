# Contributing a skill to WebTigers Skills

A skill here is **know-how**, not capability — instructions that steer an agent over tools it already
has, never new executable power. Keep every skill grounded in Tiger's real, documented conventions.

## Add a skill

1. Create `skills/<your-skill>/SKILL.md` with frontmatter carrying **exactly two fields**:

   ```markdown
   ---
   name: tiger-something
   description: One sentence — WHAT it does AND WHEN to use it (the description does double duty; a good "when" is what makes the agent reach for it at the right moment).
   ---

   # The instructions body (markdown)
   ```

   Per the Agent Skills spec the frontmatter is `name` + `description` only. Optional bundled resources
   (reference docs, data files) live alongside `SKILL.md` in the same folder and are pulled in on demand.

2. Add a matching entry to `.claude-plugin/marketplace.json`:

   ```json
   { "name": "tiger-something", "description": "...", "source": "./skills/tiger-something", "category": "Tiger" }
   ```

3. Run the validator, then open a PR:

   ```
   python3 .github/scripts/validate_skills.py
   ```

   CI runs exactly this on every PR. It checks that each `SKILL.md` opens with a closed frontmatter
   fence carrying **only** `name` and `description`, that `name` matches its directory, that every
   skill is published in `marketplace.json` and every manifest `source` still resolves to a real
   folder with a `SKILL.md`, that relative links resolve, and that no `§N` cross-reference points at
   a section the document doesn't have — the one that bites when you renumber sections.

   Manifest descriptions are marketplace-facing copy and are deliberately *not* required to match
   the `description` in `SKILL.md`.

## Quality bar

- **Accurate over impressive.** Ground every claim in the platform docs (`AGENTS.md`, `WEBSERVICES.md`,
  `ADMIN.md`, `ARCHITECTURE.md`, `THEMES.md`, `CODE.md`, `TIGERSKILLS.md` in a Tiger repo). If you can't
  point at where a convention comes from, don't assert it.
- **Know-how, not capability.** New capability belongs in a Tiger *module* (an `/api` service that becomes
  an ACL-gated tool), never a script smuggled into a skill.
- **Portable.** Write to the `SKILL.md` standard so the skill works in Claude Code and the Claude API too,
  not just TigerAgent.
- **A sharp `description`.** It's the whole reason an agent picks the skill — say what it does *and* the
  trigger to use it.

## License

By contributing you agree your contribution is licensed under the repo's [BSD-3-Clause](LICENSE) license.
If a skill **ports upstream work**, keep the upstream license + attribution inside that skill's folder
(e.g. an MIT `NOTICE`); it governs that skill, and the manifest entry should name the origin.
