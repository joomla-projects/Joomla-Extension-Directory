#!/usr/bin/env python3
"""
OWASP ZAP active scan of the front end, as a logged-in user.

    tests/security/zap-active.py BASE_URL USERNAME PASSWORD [--minutes 30] [--out DIR]

BASE_URL is the address the scanner container can reach, for example
http://host.docker.internal:8080/jed/ . The script logs in once (from this machine, through
localhost unless --login-url says otherwise) with plain HTTP (Joomla's form token
has a random name per session, which ZAP's own form authentication cannot follow), hands the session
cookie to ZAP, seeds the main front-end views, keeps the scan out of /administrator and logout URLs,
and writes a JSON and an HTML report.

ONLY RUN THIS AGAINST A THROWAWAY COPY OF THE SITE. An active scan submits thousands of requests,
including forms, and creates junk records.
"""
import argparse
import http.cookiejar
import os
import re
import subprocess
import sys
import urllib.parse
import urllib.request

VIEWS = [
    'option=com_jed&view=extensions',
    'option=com_jed&view=categories',
    'option=com_jed&view=dashboard',
    'option=com_jed&view=reviews',
    'option=com_jed&view=newextension',
    'option=com_tickets&view=tickets',
    'option=com_tickets&view=ticketform&layout=edit',
    'option=com_abandonware&view=abandoned',
    'option=com_abandonware&view=report',
    'option=com_finder&view=search&q=extension',
    'option=com_users&view=registration',
    'option=com_users&view=login',
]


def login(base, user, password):
    jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    html = opener.open(base + 'index.php?option=com_users&view=login', timeout=30).read().decode('utf8', 'replace')
    token = re.search(r'name="([a-f0-9]{32})" value="1"', html)
    if not token:
        sys.exit('could not find the login form token')
    data = urllib.parse.urlencode({'username': user, 'password': password, 'task': 'user.login', 'return': '', token.group(1): '1'}).encode()
    opener.open(urllib.request.Request(base + 'index.php?option=com_users&task=user.login', data), timeout=30).read()
    page = opener.open(base + 'index.php', timeout=30).read().decode('utf8', 'replace')
    if 'user.logout' not in page:
        sys.exit('login failed: the page does not offer a logout link')
    return '; '.join(f'{c.name}={c.value}' for c in jar)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('base')
    ap.add_argument('user')
    ap.add_argument('password')
    ap.add_argument('--minutes', type=int, default=30, help='longest the active scan may run')
    ap.add_argument('--spider-minutes', type=int, default=6)
    ap.add_argument('--login-url', help='address of the site from this machine; default: BASE_URL with host.docker.internal replaced by localhost')
    ap.add_argument('--out', default=os.path.join(os.getcwd(), 'zap-active-report'))
    a = ap.parse_args()
    base = a.base if a.base.endswith('/') else a.base + '/'

    login_base = a.login_url or base.replace('host.docker.internal', 'localhost')
    cookie = login(login_base if login_base.endswith('/') else login_base + '/', a.user, a.password)
    seeds = '\n'.join(f'      - url: "{base}index.php?{v}"\n        method: GET' for v in VIEWS)
    plan = f'''env:
  contexts:
    - name: jed
      urls: ["{base}"]
      includePaths: ['{re.escape(base)}.*']
      excludePaths: [".*logout.*", ".*/administrator.*"]
  parameters:
    failOnError: false
    failOnWarning: false
    progressToStdout: true
jobs:
  - type: replacer
    parameters:
      deleteAllRules: true
    rules:
      - description: session
        matchType: REQ_HEADER
        matchString: Cookie
        matchRegex: false
        replacementString: "{cookie}"
  - type: requestor
    requests:
{seeds}
  - type: spider
    parameters: {{context: jed, maxDuration: {a.spider_minutes}, maxDepth: 6}}
  - type: passiveScan-wait
    parameters: {{maxDuration: 5}}
  - type: activeScan
    parameters: {{context: jed, maxRuleDurationInMins: 3, maxScanDurationInMins: {a.minutes}, threadPerHost: 3}}
  - type: passiveScan-wait
    parameters: {{maxDuration: 5}}
  - type: report
    parameters: {{template: traditional-json, reportDir: /zap/wrk, reportFile: zap-report}}
  - type: report
    parameters: {{template: traditional-html, reportDir: /zap/wrk, reportFile: zap-report}}
'''
    os.makedirs(a.out, exist_ok=True)
    os.chmod(a.out, 0o777)
    plan_file = os.path.join(a.out, 'plan.yaml')
    with open(plan_file, 'w') as f:
        f.write(plan)
    os.chmod(plan_file, 0o644)  # the container user must read it; it is deleted after the run
    subprocess.run(['docker', 'run', '--rm', '-v', f'{a.out}:/zap/wrk:rw', 'ghcr.io/zaproxy/zaproxy:stable', 'zap.sh', '-cmd', '-autorun', '/zap/wrk/plan.yaml'], check=False)
    os.remove(plan_file)  # the plan holds a session cookie: do not leave it behind
    report = os.path.join(a.out, 'zap-report.html')
    if not os.path.exists(report):
        sys.exit('ZAP finished without writing a report; see its output above')
    print(f'Report: {report}')


if __name__ == '__main__':
    main()
