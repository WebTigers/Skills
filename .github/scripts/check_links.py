#!/usr/bin/env python3
"""
Check that every external link in every SKILL.md still resolves.

Why this is separate from validate_skills.py: that one is pure offline structure and gates every PR.
This one touches the network, so it must never be the reason a good PR is blocked by a transient
failure or a rate limit. It runs on a schedule instead — drift gets caught within a day, which is the
right trade for a link that lives in another repo.

It matters because these skills deliberately LINK to canonical docs rather than fork copies of them.
That is the right call — a forked doc drifts silently — but it means renaming a file in another repo
breaks this one, and nothing here would notice. An agent mid-install then cannot fetch the detail it
was told to go and read.

Run locally:  python3 .github/scripts/check_links.py
"""

import os
import re
import sys
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SKILLS = os.path.join(ROOT, 'skills')
TIMEOUT = 25
UA = 'Mozilla/5.0 (compatible; webtigers-skills-linkcheck/1.0)'

LINK = re.compile(r'\[[^\]]*\]\((https?://[^)\s]+)\)')


def raw_equivalent(url):
    """For a github blob URL, also try raw.githubusercontent — that is what an agent actually fetches."""
    m = re.match(r'https://github\.com/([^/]+)/([^/]+)/blob/([^/]+)/(.+)$', url)
    if not m:
        return None
    owner, repo, ref, path = m.groups()
    return 'https://raw.githubusercontent.com/%s/%s/%s/%s' % (owner, repo, ref, path)


def check(url):
    """Return (ok, detail). A 403 is reported but not failed — bot protection is not a dead link."""
    req = urllib.request.Request(url, headers={'User-Agent': UA})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return True, 'HTTP %d' % r.status
    except urllib.error.HTTPError as e:
        if e.code in (403, 429):
            return True, 'HTTP %d (bot-protected or rate-limited — not treated as broken)' % e.code
        return False, 'HTTP %d' % e.code
    except Exception as e:
        return False, type(e).__name__ + ': ' + str(e)[:80]


def main():
    urls = {}
    for slug in sorted(os.listdir(SKILLS)):
        path = os.path.join(SKILLS, slug, 'SKILL.md')
        if not os.path.isfile(path):
            continue
        with open(path, encoding='utf-8') as fh:
            for u in LINK.findall(fh.read()):
                urls.setdefault(u, []).append(slug)

    if not urls:
        print('No external links found.')
        return 0

    failures = []
    print('Checking %d external link(s)\n' % len(urls))
    for url, slugs in sorted(urls.items()):
        ok, detail = check(url)
        print('  %-7s %s' % ('ok' if ok else 'BROKEN', url))
        print('          %s  (in: %s)' % (detail, ', '.join(sorted(set(slugs)))))
        if not ok:
            failures.append((url, detail, slugs))
            continue

        # A github blob URL that resolves in a browser can still 404 as raw — and raw is the one an
        # agent fetches. Check both so a moved file cannot hide behind the pretty URL.
        raw = raw_equivalent(url)
        if raw:
            rok, rdetail = check(raw)
            print('          raw: %s %s' % ('ok' if rok else 'BROKEN', rdetail))
            if not rok:
                failures.append((raw, rdetail, slugs))

    if failures:
        print('\n%d broken link(s):' % len(failures))
        for url, detail, slugs in failures:
            print('  %s  — %s  (in: %s)' % (url, detail, ', '.join(sorted(set(slugs)))))
        return 1
    print('\nAll links resolve.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
