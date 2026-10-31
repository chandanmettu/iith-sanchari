#!/usr/bin/env python3
"""Build Sanchari's server-only api/config.php on a Mac, without PHP.

It generates the ticket signing secret, one access key per staff member (only a bcrypt
hash goes in the file, made with macOS's htpasswd) and the webhook login, asks for the
database login, and writes the file with owner-only permissions outside this repository.
Upload the result to public_html/api/config.php with Hostinger's File Manager.

Payment values can stay blank until the gateway is approved, and booking always starts
switched off: BOOKING_ENABLED and OPERATIONS_CONFIRMED are launch decisions made by hand.

Usage: python3 tools/make-config.py "/path/outside/the/repo/config.php"
"""
import getpass
import os
import re
import secrets
import subprocess
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
STAFF_ID = re.compile(r'^[A-Za-z0-9._-]{1,64}$')


def ask(label, default=None, secret=False, optional=False):
    hint = f' [{default}]' if default else ' (blank to fill in later)' if optional else ''
    while True:
        value = (getpass.getpass if secret else input)(f'{label}{hint}: ').strip()
        if not value and default is not None:
            return default
        if value or optional:
            return value


def minutes(label):
    while True:
        value = ask(label, optional=True)
        if not value:
            return None
        if value.isdigit():
            return int(value)
        print('  Enter whole minutes, or leave it blank.')


def staff_ids(label):
    while True:
        ids = [x.strip() for x in ask(label, optional=True).split(',') if x.strip()]
        if all(STAFF_ID.match(x) for x in ids):
            return ids
        print('  Use letters, numbers, dot, dash or underscore, separated by commas.')


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
    if len(sys.argv) != 2:
        sys.exit(__doc__.strip().splitlines()[-1])
    out = Path(sys.argv[1]).expanduser().resolve()
    if out == REPO or REPO in out.parents:
        sys.exit('Write the file outside the repository: anything committed here is public.')
    if out.exists() and ask(f'{out} exists. Overwrite? (yes/no)') != 'yes':
        sys.exit('Nothing written.')

    print('\nDatabase (create it in hPanel > Databases, then import api/schema.sql in phpMyAdmin)')
    host = ask('  Host', 'localhost')
    name = ask('  Database name')
    user = ask('  Database user')
    password = ask('  Database password', secret=True)

    print('\nBoarding window (both values must be approved by Transport before launch)')
    before = minutes('  Minutes before departure a pass becomes valid')
    after = minutes('  Minutes after departure it stays valid')

    print('\nStaff (each person gets their own key; only its hash is stored)')
    staff, keys = {}, {}
    for role, label in (('driver', '  Driver IDs, comma separated'), ('admin', '  Office IDs, comma separated')):
        for sid in staff_ids(label):
            keys[sid] = secrets.token_urlsafe(12)
            staff[sid] = {'password_hash': bcrypt(keys[sid]), 'role': role}

    gateway = {'PAYMENT_PROTOCOL': '', 'PAYMENT_CLIENT_ID': '', 'PAYMENT_CLIENT_SECRET': '', 'PAYMENT_CLIENT_VERSION': '',
               'PAYMENT_AUTH_URL': '', 'PAYMENT_BASE_URL': '', 'CHECKOUT_HOSTS': []}
    if ask('\nFill in the payment gateway now? (yes/no)', 'no') == 'yes':
        gateway.update(
            PAYMENT_PROTOCOL=ask('  Protocol', 'oauth-checkout-v2'),
            PAYMENT_CLIENT_ID=ask('  Client ID'),
            PAYMENT_CLIENT_SECRET=ask('  Client secret', secret=True),
            PAYMENT_CLIENT_VERSION=ask('  Client version'),
            PAYMENT_AUTH_URL=ask('  Auth URL (exact, https)'),
            PAYMENT_BASE_URL=ask('  Base URL (before /checkout/v2)'),
            CHECKOUT_HOSTS=[h.strip() for h in ask('  Checkout redirect hosts, comma separated').split(',') if h.strip()],
        )
    webhook_user, webhook_pass = 'sanchari-' + secrets.token_hex(3), secrets.token_urlsafe(18)

    config = {
        'APP_ENV': 'production', 'BOOKING_ENABLED': False, 'OPERATIONS_CONFIRMED': False, 'ORDER_RATE_PER_MIN': 60,
        'PUBLIC_URL': 'https://sanchari.iith.online',
        'DB_DSN': f'mysql:host={host};dbname={name};charset=utf8mb4', 'DB_USER': user, 'DB_PASSWORD': password,
        'TICKET_HMAC_SECRET': secrets.token_hex(32), **gateway,
        'WEBHOOK_USERNAME': webhook_user, 'WEBHOOK_PASSWORD': webhook_pass,
        'BOARDING_BEFORE_MIN': before, 'BOARDING_AFTER_MIN': after, 'STAFF': staff,
    }
    body = '<?php\n// Server-only Sanchari configuration made by tools/make-config.py. Never commit or share this file.\nreturn [\n'
    body += ''.join(f'    {php(k)} => {php(v)},\n' for k, v in config.items()) + '];\n'
    out.parent.mkdir(parents=True, exist_ok=True)
    fd = os.open(out, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w') as f:
        f.write(body)
    os.chmod(out, 0o600)

    print(f'\nWrote {out} (readable only by you).')
    print('Upload it to public_html/api/config.php in Hostinger File Manager, then delete any other copies.')
    if keys:
        print('\nStaff access keys. Hand each one over privately; they are not saved anywhere:')
        for sid, key in keys.items():
            print(f'  {sid:<20} {staff[sid]["role"]:<7} {key}')
    print('\nWebhook login for the gateway dashboard (its "SHA256 username:password" authentication):')
    print(f'  username {webhook_user}\n  password {webhook_pass}')
    print('\nStill off on purpose: BOOKING_ENABLED, OPERATIONS_CONFIRMED and "confirmed" in assets/routes.json.')


if __name__ == '__main__':
    main()
