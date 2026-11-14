#!/usr/bin/env python3
"""
Sends injection and XSS payloads through the front-end forms (review, ticket) and the read-only
list and detail views of a LOCAL TEST COPY of the site, using a throwaway registered user and valid
form tokens, then checks two things:

  * no response contains SQL or PHP error text, and
  * none of the markup payloads comes back unescaped on the pages that display user text.

A scanner such as ZAP cannot do this for Joomla forms: every POST needs the session's form token,
whose name is random. Run it with the site's database reachable through the local `mysql` client
(root over the socket), which it uses to publish the test records and to find an extension.

    tests/security/fuzz-forms.py --base http://localhost:8080/jed --site-dir /var/www/jed \
        --db jed --prefix jos_

Exit status is 1 when something was found.
"""
import argparse
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import http.cookiejar
import uuid

PAYLOADS = [
    ('sqli-quote', "a'\""),
    ('sqli-or', "' OR '1'='1' -- "),
    ('sqli-union', '1) UNION SELECT user(),2,3-- -'),
    ('sqli-sleep', "'; SELECT SLEEP(3)-- "),
    ('xss-script', '<script>window.__p1=1</script>'),
    ('xss-img', '<img src=x onerror=window.__p2=1>'),
    ('xss-svg', '"><svg/onload=window.__p3=1>'),
    ('xss-attr', '" onmouseover="window.__p4=1" x="'),
    ('template', '{{7*7}}${7*7}<%= 7*7 %>'),
    ('path', '../../../../etc/passwd%00'),
    ('crlf', 'a%0d%0aSet-Cookie: pwn=1'),
    ('long', 'A' * 20000),
]
RAW_MARKUP = [r'<script>window\.__p1', r'<img src=x onerror=window\.__p2', r'<svg/onload=window\.__p3', r'" onmouseover="window\.__p4']
ERROR_TEXT = re.compile(r'(You have an error in your SQL|SQLSTATE|mysqli_sql_exception|Unknown column|Table .* doesn.t exist|Fatal error|Stack trace|Warning:|Deprecated:)', re.I)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--base', required=True, help='site address, e.g. http://localhost:8080/jed')
    ap.add_argument('--site-dir', required=True, help='Joomla root on this machine (its CLI creates the throwaway user)')
    ap.add_argument('--db', required=True, help='database name')
    ap.add_argument('--prefix', required=True, help='table prefix, e.g. jos_')
    a = ap.parse_args()
    base = a.base.rstrip('/')

    def sql(q):
        return subprocess.run(['mysql', '-uroot', a.db, '-N', '-e', q], capture_output=True, text=True).stdout.strip()

    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def fetch(url, data=None):
        req = urllib.request.Request(url, urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            r = opener.open(req, timeout=60)
            return r.status, r.read().decode('utf8', 'replace')
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode('utf8', 'replace')

    def get(path):
        return fetch(f'{base}/{path}')

    def post(path, data):
        return fetch(f'{base}/{path}', data)

    def token(html):
        m = re.search(r'name="([a-f0-9]{32})" value="1"', html)
        return m.group(1) if m else None

    ext = sql(f"select concat(id,' ',catid) from {a.prefix}jed_extensions where approved=1 and state=1 and blocked=0 and deleted=0 order by id desc limit 1").split()
    if len(ext) != 2:
        sys.exit('no published extension found to review')
    ext_id, cat_id = ext

    user = 'fuzz' + uuid.uuid4().hex[:6]
    password = 'Fuzz-test-pass-2026!'
    subprocess.run(['php', 'cli/joomla.php', 'user:add', f'--username={user}', '--name=Fuzz User', f'--email={user}@example.test', f'--password={password}', '--usergroup=Registered', '-n'], cwd=a.site_dir, capture_output=True)
    t = token(get('index.php?option=com_users&view=login')[1])
    post('index.php?option=com_users&task=user.login', {'username': user, 'password': password, 'task': 'user.login', 'return': '', t: '1'})
    if 'user.logout' not in get('index.php')[1]:
        sys.exit('could not log in as the throwaway user')

    findings = []
    runs = 0
    for name, p in PAYLOADS:
        q = urllib.parse.quote(p, safe='')
        for form, path in (
            ('finder search', f'index.php?option=com_finder&view=search&q={q}'),
            ('extensions filters', f'index.php?option=com_jed&view=extensions&filter[search]={q}&list[fullordering]={q}&list[limit]={q}&limitstart={q}'),
            ('category id', f'index.php?option=com_jed&view=category&id={q}'),
            ('extension id', f'index.php?option=com_jed&view=extension&catid={q}&id={q}'),
            ('review id', f'index.php?option=com_jed&view=review&id={q}'),
        ):
            runs += 1
            _, body = get(path)
            m = ERROR_TEXT.search(body)
            if m:
                findings.append(f'{form} / {name}: response contains "{m.group(1)}"')

        value = f'{uuid.uuid4().hex[:8]} {p}'
        t = token(get(f'index.php?option=com_jed&view=reviewform&layout=edit&catid={cat_id}&id={ext_id}')[1])
        _, body = post('index.php?option=com_jed&task=reviewform.save', {
            'option': 'com_jed', 'task': 'reviewform.save', t: '1', 'jform[id]': '0', 'jform[extension_id]': ext_id,
            'jform[title]': value, 'jform[functionality]': '4', 'jform[ease_of_use]': '4', 'jform[functionality_comment]': value,
            'jform[ease_of_use_comment]': value, 'jform[used_for]': value, 'jform[version]': value[:40],
        })
        runs += 1
        if ERROR_TEXT.search(body):
            findings.append(f'review form / {name}: response contains error text')

        t = token(get('index.php?option=com_tickets&view=ticketform&layout=edit')[1])
        _, body = post('index.php?option=com_tickets&task=ticketform.save', {
            'option': 'com_tickets', 'task': 'ticketform.save', t: '1', 'jform[id]': '0', 'jform[ticket_category_type]': '0',
            'jform[ticket_subject]': value, 'jform[ticket_text]': value,
        })
        runs += 1
        if ERROR_TEXT.search(body):
            findings.append(f'ticket form / {name}: response contains error text')

    uid = sql(f"select id from {a.prefix}users where username='{user}'")
    sql(f"update {a.prefix}jed_reviews set state=1 where created_by={uid}")
    review = sql(f"select id from {a.prefix}jed_reviews where created_by={uid} order by id desc limit 1")
    ticket = sql(f"select id from {a.prefix}jed_tickets where created_by={uid} order by id desc limit 1")
    pages = [f'index.php?option=com_jed&view=extension&catid={cat_id}&id={ext_id}', 'index.php?option=com_jed&view=reviews',
             'index.php?option=com_jed&view=dashboard', 'index.php?option=com_tickets&view=tickets']
    if review:
        pages.append(f'index.php?option=com_jed&view=review&id={review}')
    if ticket:
        pages.append(f'index.php?option=com_tickets&view=ticket&id={ticket}')
    for path in pages:
        body = get(path)[1]
        for pattern in RAW_MARKUP:
            for m in re.finditer(pattern, body):
                findings.append(f'unescaped payload on {path}: ...{body[max(0, m.start() - 30):m.start() + 50]!r}')

    print(f'{runs} requests, {len(pages)} pages checked for unescaped payloads, user {user}')
    if findings:
        print(f'{len(findings)} finding(s):')
        for f in findings[:25]:
            print('  -', f)
        sys.exit(1)
    print('no SQL/PHP error text, no unescaped payloads')


if __name__ == '__main__':
    main()
