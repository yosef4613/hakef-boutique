#!/usr/bin/env python3
"""Small client for the Hostinger file browser REST API (same temporary credentials as the uploads).

usage:
  python3 fb.py ls  <path-in-public_html>
  python3 fb.py rm  <path-in-public_html> [<path> ...]      # deletes files or whole directories
  python3 fb.py mv  <from> <to>                              # rename / move inside public_html
"""
import json, subprocess, sys, urllib.parse

import os
creds = json.load(open(os.environ.get('HAKEF_CREDS', __file__.rsplit('/', 1)[0] + '/creds.json')))
API = creds['url'].split('/api/')[0] + '/api'
H = ['-H', 'X-Auth: ' + creds['auth_key'], '-H', 'X-Auth-Rest: ' + creds['rest_auth_key']]


def res_url(path):
    return API + '/resources/public_html/' + urllib.parse.quote(path.strip('/'), safe='/')


def run(args):
    r = subprocess.run(['curl', '-s', '-w', '\n%{http_code}', '--max-time', '300'] + H + args, capture_output=True, text=True)
    body, _, code = r.stdout.rpartition('\n')
    return code, body


cmd = sys.argv[1]
if cmd == 'ls':
    code, body = run([res_url(sys.argv[2]) + '/'])
    if code == '200':
        d = json.loads(body)
        for it in d.get('items', []):
            print(('d ' if it.get('isDir') else 'f ') + it['name'], it.get('size', ''))
    else:
        print(code, body[:300])
elif cmd == 'rm':
    for path in sys.argv[2:]:
        code, body = run(['-X', 'DELETE', res_url(path)])
        print(code, path, body[:120] if code not in ('200', '204') else '')
elif cmd in ('mv', 'cp'):
    src, dst = sys.argv[2], sys.argv[3]
    dest = urllib.parse.quote('/public_html/' + dst.strip('/'), safe='/')
    action = 'rename' if cmd == 'mv' else 'copy'
    code, body = run(['-X', 'PATCH', res_url(src) + f'?action={action}&override=true&rename=false&destination=' + dest])
    print(code, cmd, src, '->', dst, body[:160] if code not in ('200', '204') else '')
