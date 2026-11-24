#!/usr/bin/env python3
"""Builds upload manifests.
usage: python3 manifest.py wp|stage|golive OUT.json
  wp     : theme + recipe mu-plugins into wp-content
  stage  : preview build (noindex) into _hakef_new/ + a test .htaccess
  golive : production build (dist/) into the site root (without .htaccess; swapped separately)
"""
import json, os, sys
REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
kind, out = sys.argv[1], sys.argv[2]
items = []


def walk(src, dst_prefix, skip=()):
    for root, _, files in os.walk(src):
        for f in files:
            full = os.path.join(root, f)
            rel = os.path.relpath(full, src)
            if any(rel == s or rel.startswith(s + os.sep) for s in skip):
                continue
            items.append({'local': full, 'remote': (dst_prefix + rel).replace(os.sep, '/')})


if kind == 'wp':
    walk(os.path.join(REPO, 'wp', 'themes', 'hakef'), 'wp-content/themes/hakef/')
    walk(os.path.join(REPO, 'wp', 'mu-plugins'), 'wp-content/mu-plugins/')
elif kind == 'stage':
    walk(os.path.join(REPO, 'site'), '_hakef_new/', skip=('sitemap.xml', 'robots.txt'))
elif kind == 'golive':
    walk(os.path.join(REPO, 'dist'), '', skip=('.htaccess',))
json.dump(items, open(out, 'w'), ensure_ascii=False, indent=0)
print(kind, len(items), 'files', sum(os.path.getsize(i['local']) for i in items) // 1024, 'KB')
