# Security testing

How to test the front end against the OWASP Top 10 on a local copy of the site, what such a run
found, and the deployment settings that go with it. Everything here is for a **throwaway test
site**: the active scans submit thousands of requests and create junk records.

## What is automated

| Tool | What it covers | Run it |
|---|---|---|
| PHPUnit (`tests/unit`) | access rules, escaping helpers, Markdown output, limits | `vendor/bin/phpunit` |
| Cypress (`tests/cypress`) | workflows, and the negative paths: ownership, tokens, escaping, no PHP messages on the main pages | `npx cypress run --e2e` |
| `composer audit` (CI job `composer-audit`) | published advisories in the locked dependencies | `composer audit --no-dev --locked` (run in the repo root and in `src/administrator/components/com_jed`) |
| `tests/security/zap-baseline.sh` | OWASP ZAP passive scan: headers, cookies, information disclosure | see below |
| `tests/security/zap-active.py` | OWASP ZAP active scan as a logged-in user: injection, XSS, path traversal, error disclosure | see below |
| `tests/security/fuzz-forms.py` | injection and XSS payloads through the real forms with valid tokens | see below |

### ZAP baseline (safe, passive)

```bash
tests/security/zap-baseline.sh http://host.docker.internal:8080/jed/ ./zap-baseline-report
```

Needs Docker. Inside the container `localhost` is the container, so use `host.docker.internal`
(Docker Desktop, OrbStack) or the host's address.

### ZAP active scan (intrusive)

```bash
tests/security/zap-active.py http://host.docker.internal:8080/jed/ SOME_USER 'PASSWORD' --out ./zap-active-report
```

Logs in once and gives ZAP the session cookie (Joomla's form token has a random name per session,
which ZAP's own form authentication cannot follow), seeds the main front-end views, and keeps out of
`/administrator` and logout URLs. `--minutes` bounds the active scan (default 30).

### Form payloads

```bash
tests/security/fuzz-forms.py --base http://localhost:8080/jed --site-dir /path/to/joomla --db jed --prefix jos_
```

Needs the local `mysql` client (root over the socket). A scanner cannot do this part for Joomla
forms: every POST needs the session's form token, so a scan's payloads mostly hit the token check.
This script submits 12 payload families (SQL injection variants, script/img/svg/attribute XSS,
template syntax, path traversal, header injection, a 20 kB value) through the review and ticket
forms and the list and detail views, then checks the responses for SQL/PHP error text and the
rendered pages for unescaped markup. It exits 1 when it finds something.

## What a run found

Run on a Joomla 6.2 test site, unpatched `main` against the same code with the pull requests that
came out of the security review applied (#74 to #89).

| Finding | `main` | with the fixes |
|---|---|---|
| Application error disclosure: PHP warnings printing server paths on the review form and ticket form | 4 pages | 0 |
| Private IP disclosure: reviewer IP addresses on public review pages | 2 pages | 0 |
| Stored markup rendered unescaped (form payloads) | 4 places | 0 |
| SQL error text in any response | 0 | 0 |
| ZAP high-risk alerts | 0 | 0 |
| `league/commonmark` advisories in the shipped package | 6 | 0 |

Remaining ZAP alerts, none of them in the extension's code:

* **Missing security headers** (CSP, `X-Content-Type-Options`, Permissions-Policy, COEP/CORP): a
  deployment setting, see below.
* **`Server` and `X-Powered-By` banners**: web server and PHP settings, see below.
* **Cookie without `SameSite`**: set by Joomla core's session handling.
* **Vulnerable JS library**: DOMPurify inside the TinyMCE that Joomla core ships.
* **"Absence of anti-CSRF tokens"** on the login forms is a false positive. Joomla's login forms do
  carry a token; its name is a 32-character hex string, which ZAP's name matching does not recognise.

## Deployment settings

Verified on a test site except where marked.

### Joomla: System - HTTP Headers plugin

Enable the plugin and set X-Frame-Options, Referrer-Policy and Cross-Origin-Opener-Policy (the
defaults already send them). Add a `permissions-policy` additional header, for example
`geolocation=(), camera=(), microphone=(), payment=()`, and a Content-Security-Policy. This policy
was **enforced** (not report-only) while the front-end Cypress workflow ran, and it passed:

```
default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';
img-src 'self' data: https:; object-src 'none'; base-uri 'self'; form-action 'self';
frame-ancestors 'self'
```

`'unsafe-inline'` is needed because the pages carry inline scripts and styles; the policy still
blocks framing, plugins, `<base>` injection and form targets elsewhere. Start in report-only mode
and widen `img-src`, `script-src` or `frame-src` for anything a site embeds (video players, for
example).

In the plugin's JSON each entry needs a `client` (`site`, `administrator` or `both`), which is easy to
miss when configuring it outside the admin form.

### Apache and PHP (not verified here, standard settings)

```apache
ServerTokens Prod
ServerSignature Off
TraceEnable Off
Header always set X-Content-Type-Options "nosniff"
```

and `expose_php = Off` in `php.ini`. `TraceEnable Off` matters because Apache's default answers
`TRACE` with the request echoed back.

## Reading a ZAP report

* Treat anything Medium or above from a **GET with no parameter** as a deployment or header issue
  first.
* Check an alert's instances before acting: ZAP reports where it saw a pattern, not that the
  pattern is exploitable.
* Compare a run on unpatched code with a run on your branch, as above, so a finding can be attributed.
