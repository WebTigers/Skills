#!/usr/bin/env python3
"""
Validate every SKILL.md in this repo, and the marketplace manifest that publishes them.

These skills are installed by URL straight into someone's agent. A malformed frontmatter key or a
manifest `source` pointing at a directory that no longer exists breaks the install for everyone who
follows the published link, and it breaks silently — nothing here is exercised by a test suite.
So the checks are deliberately strict and deliberately boring.

Run locally exactly as CI does:   python3 .github/scripts/validate_skills.py
Exit 0 = clean, 1 = at least one error.
"""

import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SKILLS_DIR = os.path.join(ROOT, 'skills')
MANIFEST = os.path.join(ROOT, '.claude-plugin', 'marketplace.json')

# The Agent Skills frontmatter contract: these two keys, nothing else.
REQUIRED_KEYS = {'name', 'description'}
NAME_RE = re.compile(r'^[a-z0-9]+(-[a-z0-9]+)*$')
MAX_NAME = 64
MAX_DESC = 1024

errors = []
warnings = []


def err(where, msg):
    errors.append('%s: %s' % (where, msg))


def warn(where, msg):
    warnings.append('%s: %s' % (where, msg))


def split_frontmatter(text, where):
    """Return (dict_of_keys, body) or (None, None) having recorded an error."""
    if not text.startswith('---\n'):
        err(where, 'must open with a `---` frontmatter fence on line 1')
        return None, None
    end = text.find('\n---\n', 3)
    if end == -1:
        err(where, 'frontmatter fence is never closed')
        return None, None
    raw, body = text[4:end + 1], text[end + 5:]

    data = {}
    for i, line in enumerate(raw.rstrip('\n').split('\n'), start=2):
        if not line.strip():
            continue
        if line[0] in ' \t':
            err(where, 'line %d: frontmatter must be flat `key: value` — no indented or '
                       'multi-line values (got %r)' % (i, line[:40]))
            return None, None
        if ':' not in line:
            err(where, 'line %d: not a `key: value` pair (%r)' % (i, line[:40]))
            return None, None
        k, v = line.split(':', 1)
        k = k.strip()
        if k in data:
            err(where, 'duplicate frontmatter key %r' % k)
            return None, None
        data[k] = v.strip()
    return data, body


def check_skill(slug):
    where = 'skills/%s/SKILL.md' % slug
    path = os.path.join(SKILLS_DIR, slug, 'SKILL.md')
    if not os.path.isfile(path):
        err('skills/%s' % slug, 'directory has no SKILL.md')
        return None

    with open(path, encoding='utf-8') as fh:
        text = fh.read()

    data, body = split_frontmatter(text, where)
    if data is None:
        return None

    extra = set(data) - REQUIRED_KEYS
    missing = REQUIRED_KEYS - set(data)
    if missing:
        err(where, 'frontmatter missing %s' % ', '.join(sorted(missing)))
    if extra:
        err(where, 'frontmatter has unsupported key(s) %s — the format is exactly '
                   'name + description' % ', '.join(sorted(extra)))

    name = data.get('name', '')
    if name:
        if name != slug:
            err(where, 'name %r does not match its directory %r' % (name, slug))
        if not NAME_RE.match(name):
            err(where, 'name %r must be lowercase kebab-case' % name)
        if len(name) > MAX_NAME:
            err(where, 'name is %d chars (max %d)' % (len(name), MAX_NAME))

    desc = data.get('description', '')
    if not desc:
        err(where, 'description is empty — it is what decides when the skill fires')
    elif len(desc) > MAX_DESC:
        err(where, 'description is %d chars (max %d)' % (len(desc), MAX_DESC))

    if body is not None and not body.strip():
        err(where, 'body is empty')

    check_section_refs(text, where)
    check_relative_links(text, where, slug)
    return name


def check_section_refs(text, where):
    """A `§N` pointing at a section number the document does not have.

    This is the failure mode that renumbering causes: every reference still looks fine, and one of
    them now sends the reader to the wrong step. External refs (`INSTALLER.md §4`) are not ours.
    """
    sections = {int(m) for m in re.findall(r'^#{2,3} (\d+)\.', text, re.M)}
    if not sections:
        return
    for m in re.finditer(r'(\S+\.md\s+)?§(\d+)', text):
        if m.group(1):          # qualified by a filename => another document's numbering
            continue
        n = int(m.group(2))
        if n not in sections:
            err(where, '§%d does not exist in this document (has %s)'
                % (n, ', '.join('§%d' % s for s in sorted(sections))))


def check_relative_links(text, where, slug):
    """Markdown links to files inside the skill folder must actually resolve."""
    base = os.path.join(SKILLS_DIR, slug)
    for m in re.finditer(r'\[[^\]]*\]\(([^)]+)\)', text):
        target = m.group(1).split('#')[0].strip()
        if not target or target.startswith(('http://', 'https://', 'mailto:', '#')):
            continue
        if os.path.isabs(target):
            warn(where, 'link %r is an absolute path' % target)
            continue
        if not os.path.exists(os.path.join(base, target)):
            err(where, 'link %r does not resolve' % target)


def check_manifest(skill_names):
    where = '.claude-plugin/marketplace.json'
    if not os.path.isfile(MANIFEST):
        err(where, 'missing')
        return
    try:
        with open(MANIFEST, encoding='utf-8') as fh:
            mf = json.load(fh)
    except ValueError as exc:
        err(where, 'invalid JSON: %s' % exc)
        return

    plugins = mf.get('plugins')
    if not isinstance(plugins, list):
        err(where, '`plugins` must be a list')
        return

    listed = set()
    for i, p in enumerate(plugins):
        tag = '%s [plugins/%d]' % (where, i)
        name, source = p.get('name'), p.get('source')
        if not name:
            err(tag, 'entry has no `name`')
            continue
        listed.add(name)
        if not p.get('description'):
            err(tag, '%s has no `description`' % name)
        if not source:
            err(tag, '%s has no `source`' % name)
            continue

        # the check that matters most: does the published path still exist?
        resolved = os.path.normpath(os.path.join(ROOT, source.lstrip('./')))
        if not os.path.isdir(resolved):
            err(tag, '%s source %r does not exist' % (name, source))
        elif not os.path.isfile(os.path.join(resolved, 'SKILL.md')):
            err(tag, '%s source %r has no SKILL.md' % (name, source))
        elif os.path.basename(resolved) != name:
            err(tag, '%s source %r points at directory %r'
                % (name, source, os.path.basename(resolved)))

    for missing in sorted(skill_names - listed):
        err(where, 'skill %r exists but is not published in the manifest' % missing)
    # NOTE: manifest descriptions are intentionally separate marketplace-facing copy and are
    # deliberately NOT required to match the SKILL.md description.


def main():
    if not os.path.isdir(SKILLS_DIR):
        print('no skills/ directory', file=sys.stderr)
        return 1

    slugs = sorted(d for d in os.listdir(SKILLS_DIR)
                   if os.path.isdir(os.path.join(SKILLS_DIR, d)) and not d.startswith('.'))
    if not slugs:
        print('no skills found', file=sys.stderr)
        return 1

    names = set()
    for slug in slugs:
        n = check_skill(slug)
        if n:
            names.add(n)
    check_manifest(names)

    print('Checked %d skill(s): %s\n' % (len(slugs), ', '.join(slugs)))
    for w in warnings:
        print('  warning  %s' % w)
    for e in errors:
        print('  ERROR    %s' % e)

    if errors:
        print('\n%d error(s).' % len(errors))
        return 1
    print('All skills valid.%s' % (' %d warning(s).' % len(warnings) if warnings else ''))
    return 0


if __name__ == '__main__':
    sys.exit(main())
