Joomla! Extensions Directory
============================

Build Status
---------------------
[![CI](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml/badge.svg)](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-green)](https://www.php.net/)

The set of components which power the Joomla Extensions Directory (extensions.joomla.org):

* **com_jed** — the extensions directory itself (browsing, submitting, reviewing and approving extensions, categories, scores).
* **com_tickets** — the internal support/workflow queue used by the JED team (approvals, flagged reviews).

See [`docs/features.md`](docs/features.md) for a detailed walkthrough of what each
component does, and [`docs/datastructure.md`](docs/datastructure.md) for how the
database schema across these components fits together.

Original Specifications Document from 2020: https://drive.google.com/file/d/1G4M-5jAABBIUEq3gLE9W6WxMcgZxVJYx/view?usp=sharing

Targets Joomla 6. Installation on older Joomla versions is not supported.

Requirements
------------
* PHP 8.3+
* [Composer](https://getcomposer.org/)
* Node.js + npm (only needed to run the Cypress end-to-end tests)

Setup
-----
```bash
composer install   # also runs `composer install` inside src/administrator/components/com_jed
npm ci              # only needed for the Cypress e2e tests
```

Building the extension package
-------------------------------
This repo is built with [JoRobo](https://github.com/joomla-projects/jorobo) (`joomla-projects/jorobo`, a dev dependency), driven by the root `RoboFile.php` and configured via `jorobo.ini` (copy `jorobo.dist.ini` to `jorobo.ini` if it doesn't exist yet — `robo build` does this automatically).

```bash
vendor/bin/robo build              # builds the package into dist/pkg-jed-current.zip
vendor/bin/robo map /path/to/joomla  # symlinks src/ into a running Joomla install for local development
vendor/bin/robo headers            # (re)writes copyright headers across src/, per the [header] section in jorobo.ini
vendor/bin/robo bump               # replaces __DEPLOY_VERSION__ placeholders with jorobo.ini's version
```

Note: `vendor/bin/jorobo` is a *different*, much smaller tool — it only offers `init`/`ci`/`rector` project-scaffolding subcommands and is not used to build this repo. The actual build lives in `vendor/bin/robo`.

Manifest files (`jed.xml` and friends) use JoRobo placeholder tokens (`##DATE##`, `##YEAR##`, `##VERSION##`, `##BACKEND_COMPONENT_FILES##`, etc.) that only get resolved by `vendor/bin/robo build` — don't expect them to be valid values when reading the manifests directly out of `src/`.

Linting & static analysis
--------------------------
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff   # check coding style (CI runs this and auto-commits fixes on push)
vendor/bin/php-cs-fixer fix                    # apply coding-style fixes (PSR-12 based, see .php-cs-fixer.dist.php)
vendor/bin/phpstan                             # static analysis (needs a Joomla core checkout under joomla/ to resolve core classes)
```

Testing
-------
* **Unit tests** (`vendor/bin/phpunit`): the PHPUnit scaffold exists (`tests/unit/`, `phpunit.xml.dist`) but there are currently no test cases in it.
* **End-to-end tests** (the actual functional coverage): Cypress, under `tests/cypress/`.
  ```bash
  npx cypress run --browser=chrome --e2e
  ```

CI (`.github/workflows/ci.yml`) runs, in order: `composer install` → `php-cs-fixer` → `npm ci` → `vendor/bin/robo build` (producing the package used by later steps) → `vendor/bin/phpstan` → Cypress system tests against a matrix of Joomla/PHP versions.

Installing a built package
---------------------------
Install the package produced by `vendor/bin/robo build` (or a release download) as an extension into a clean Joomla 6 installation. Do not create any users other than the admin before installing.

Once you see "Installation of the package was successful":

* Go to System → Plugins and enable **Sample Data - JED**.
* Go to the Home Dashboard and click **Install** next to **JED Sample Data** — this installs sample extensions/reviews/categories/tickets/users. It also moves your admin user to id=5 so you can still log in; the site may log you out afterwards, just log back in as your admin user.

In the admin, JED exposes Tickets, Categories and Extensions.

### Template

* Install the Joomla template from `templatework/jtemplate_4.0.9_jed` (`jtemplate_4.0.9_jedcustom.zip`).
* Go to System → Site Template Styles and select it as the default for the site.

### Front-end testing

Sample-data installation creates a test user, **testuserj5final** (password **Who0CaresF0rPasswords**), which all sample front-end data (extensions, reviews, tickets, etc.) is tied to.
