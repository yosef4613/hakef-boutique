#!/usr/bin/env python3
"""Uploads files to the website's public_html via Hostinger's TUS upload endpoint.

usage: python3 upload.py CREDS_JSON MANIFEST_JSON
  CREDS_JSON    {"url": ..., "auth_key": ..., "rest_auth_key": ...}  (temporary, from hosting_files_generate-upload-url)
  MANIFEST_JSON [{"local": "/abs/path", "remote": "relative/path/in/public_html"}, ...]
"""
import json, os, subprocess, sys, urllib.parse, concurrent.futures

creds = json.load(open(sys.argv[1]))
manifest = json.load(open(sys.argv[2]))
base = creds['url'].rstrip('/')
H = ['-H', 'X-Auth: ' + creds['auth_key'], '-H', 'X-Auth-Rest: ' + creds['rest_auth_key'], '-H', 'Tus-Resumable: 1.0.0']


def curl(args):
    r = subprocess.run(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', '--max-time', '180'] + args, capture_output=True, text=True)
    return r.stdout.strip()


def upload(item):
    local, remote = item['local'], item['remote']
    size = os.path.getsize(local)
    url = base + '/' + urllib.parse.quote(remote, safe='/') + '?override=true'
    for attempt in range(3):
        c = curl(['-X', 'POST', url] + H + ['-H', f'Upload-Length: {size}', '-H', 'Upload-Offset: 0'])
        if c not in ('200', '201', '204'):
            continue
        if size == 0:
            return remote, 'ok'
        p = curl(['-X', 'PATCH', url] + H + ['-H', 'Content-Type: application/offset+octet-stream', '-H', 'Upload-Offset: 0', '--data-binary', '@' + local])
        if p in ('200', '204'):
            return remote, 'ok'
    return remote, f'FAILED (create {c})'


with concurrent.futures.ThreadPoolExecutor(4) as ex:
    results = list(ex.map(upload, manifest))
bad = [r for r in results if r[1] != 'ok']
print(f'uploaded {len(results) - len(bad)}/{len(results)}')
for r in bad:
    print('  ', r)
sys.exit(1 if bad else 0)
