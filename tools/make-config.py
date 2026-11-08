#!/usr/bin/env python3
"""Build Sanchari's server-only api/config.php on a Mac, without PHP.

It generates the ticket signing secret, one access key per staff member (only a bcrypt hash
goes in the file, made with macOS's htpasswd) and, for test keys, a private test pass. It asks
only for the payment client ID and secret; everything else comes from the flags below. Bookings
are stored in a SQLite file the server creates by itself, so there is no database to set up.

Upload the result to public_html/api/config.php with Hostinger's File Manager, then delete
the local copy. Booking for the public always starts switched off: BOOKING_ENABLED,
OPERATIONS_CONFIRMED and "confirmed" in assets/routes.json are launch decisions made by hand.

Usage: python3 tools/make-config.py OUTPUT --env test|live --base URL --hosts HOST[,HOST]
       [--api-version V] [--drivers ID,ID] [--office ID,ID] [--before MIN] [--after MIN]
"""
import argparse
import getpass
import os
import re
import secrets
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
STAFF_ID = re.compile(r'^[A-Za-z0-9._-]{1,64}$')


def ask(label, secret=False):
    while True:
        value = (getpass.getpass if secret else input)(f'{label}: ').strip()
        if value:
            return value


def ids(value):
    out = [x.strip() for x in (value or '').split(',') if x.strip()]
    bad = [x for x in out if not STAFF_ID.match(x)]
    if bad:
        sys.exit(f'Staff IDs may use letters, numbers, dot, dash or underscore: {bad}')
    return out


def bcrypt(key):
    out = subprocess.run(['htpasswd', '-niBC', '12', 'x'], input=key + '\n', capture_output=True, text=True, check=True)
    return out.stdout.strip().split(':', 1)[1]


def php(value):
    if value is None:
        return 'null'
    if isinstance(value, bool):
        return 'true' if value else 'false'
    if isinstance(value, int):
        return str(value)
    if isinstance(value, list):
        return '[' + ', '.join(php(x) for x in value) + ']'
    if isinstance(value, dict):
        return '[' + ', '.join(f'{php(k)} => {php(v)}' for k, v in value.items()) + ']'
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"


def main():
    p = argparse.ArgumentParser(usage=__doc__.strip().split('Usage: ', 1)[1])
    p.add_argument('output')
    p.add_argument('--env', choices=['test', 'live'], required=True)
    p.add_argument('--base', required=True, help='Payment API base URL (https)')
    p.add_argument('--hosts', required=True, help='Allowed checkout page hosts, comma separated')
    p.add_argument('--api-version', default='2025-01-01')
    p.add_argument('--drivers', default='')
    p.add_argument('--office', default='')
    p.add_argument('--before', type=int, help='Minutes before departure a pass becomes valid')
    p.add_argument('--after', type=int, help='Minutes after departure it stays valid')
    a = p.parse_args()

    out = Path(a.output).expanduser().resolve()
    if out == REPO or REPO in out.parents:
        sys.exit('Write the file outside the repository: anything committed here is public.')
    if not a.base.startswith('https://'):
        sys.exit('--base must start with https://')
    if out.exists() and input(f'{out} exists. Overwrite? (yes/no): ').strip() != 'yes':
        sys.exit('Nothing written.')

    print(f'\nPaste the payment keys for the {a.env.upper()} environment (the secret stays hidden as you paste).')
    client_id = ask('  Client ID (App ID)')
    client_secret = ask('  Client secret (Secret key)', secret=True)

    staff, keys = {}, {}
    for role, group in (('driver', ids(a.drivers)), ('admin', ids(a.office))):
        for sid in group:
            keys[sid] = secrets.token_urlsafe(12)
            staff[sid] = {'password_hash': bcrypt(keys[sid]), 'role': role}

    test_key = secrets.token_urlsafe(32) if a.env == 'test' else ''
    config = {
        'APP_ENV': 'production', 'BOOKING_ENABLED': False, 'OPERATIONS_CONFIRMED': False, 'ORDER_RATE_PER_MIN': 60,
        'PUBLIC_URL': 'https://sanchari.iith.online', 'DB_DSN': '',
        'TICKET_HMAC_SECRET': secrets.token_hex(32),
        'PAYMENT_PROTOCOL': 'hosted-links-v1', 'PAYMENT_ENV': a.env, 'PAYMENT_BASE_URL': a.base.rstrip('/'),
        'PAYMENT_API_VERSION': a.api_version, 'PAYMENT_CLIENT_ID': client_id, 'PAYMENT_CLIENT_SECRET': client_secret,
        'CHECKOUT_HOSTS': [h.strip() for h in a.hosts.split(',') if h.strip()],
        'TEST_MODE_KEY': test_key,
        'BOARDING_BEFORE_MIN': a.before, 'BOARDING_AFTER_MIN': a.after, 'STAFF': staff,
    }
    body = '<?php\n// Server-only Sanchari configuration made by tools/make-config.py. Never commit or share this file.\nreturn [\n'
    body += ''.join(f'    {php(k)} => {php(v)},\n' for k, v in config.items()) + '];\n'
    out.parent.mkdir(parents=True, exist_ok=True)
    fd = os.open(out, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w') as f:
        f.write(body)
    os.chmod(out, 0o600)

    print(f'\nWrote {out} (readable only by you).')
    print('Upload it to public_html/api/config.php in Hostinger File Manager, then delete this local copy.')
    if keys:
        print('\nStaff access keys. Keep them private; they are not saved anywhere else:')
        for sid, key in keys.items():
            print(f'  {sid:<16} {staff[sid]["role"]:<7} {key}')
    if test_key:
        print('\nYour private test pass. Open this on the phone you will test with (keep it private):')
        print(f'  https://sanchari.iith.online/#test={test_key}')
        print('  To switch it off later: https://sanchari.iith.online/#test-off')
    if a.before is None or a.after is None:
        print('\nNote: --before/--after not set, so booking stays closed until a boarding window is configured.')


if __name__ == '__main__':
    main()
