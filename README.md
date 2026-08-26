Joomla! Extensions Directory
============================

Build Status
---------------------
[![CI](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml/badge.svg)](https://github.com/joomla-projects/Joomla-Extension-Directory/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%5E8.3-green)](https://www.php.net/)

The set of extensions which power the Joomla Extensions Directory (extensions.joomla.org). They are built and shipped as **one installable package, `pkg_jed`**:

* **com_jed** — the extensions directory itself (browsing, submitting, reviewing and approving extensions, categories, scores).
* **com_tickets** — the support/workflow queue connecting developers, users and the JED team (approvals, flagged reviews, link-check escalations).
* **com_abandonware** — reports and cases for extensions that appear to be no longer maintained.
* **mod_jed_extensions** (site) and **mod_jed_opentickets** (administrator).
* **Plugins** — Smart Search adapter, the scoring algorithm, the scheduled background tasks, action-log and privacy integration, and the sample-data/migration plugins.
* **tpl_joomla** — the front-end site template. It is part of the package and no longer installed separately.

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
* **Unit tests** (`vendor/bin/phpunit`): under `tests/unit/`, configured by `phpunit.xml.dist`.
* **End-to-end tests** (the actual functional coverage): Cypress, under `tests/cypress/`.
  ```bash
  npx cypress run --browser=chrome --e2e
  ```

CI (`.github/workflows/ci.yml`) runs, in order: `composer install` → `php-cs-fixer` → `npm ci` → `vendor/bin/robo build` (producing the package used by later steps) → `vendor/bin/phpstan` → Cypress system tests against a matrix of Joomla/PHP versions.

Building the CSS
-------
Run `npx sass src/media/com_jed/assets/scss/style.scss src/media/com_jed/assets/css/style.css`

Installing a built package
---------------------------
Everything installs in one step. The package produced by `vendor/bin/robo build` (`dist/pkg-jed-current.zip`) — or a release download — contains all three components, both modules, all plugins **and the site template**. There is nothing to install separately.

### 1. Install the package

Start from a clean Joomla 6 installation and install the package via System → Install → Extensions (or `php cli/joomla.php extension:install --path=/path/to/pkg-jed-current.zip`).

Do not create any users other than the admin before installing — the sample data in step 3 renumbers the admin account.

Once you see "Installation of the package was successful", the administrator menu offers **JED** (Extensions, Categories, Reviews, User Access), **Tickets** and **Abandonware**.

### 2. Select the site template

The template ships with the package, but Joomla does not make an installed template the active one. Go to **System → Site Template Styles**, open the **joomla** style and set it as the default for the site.

(The standalone template under `templatework/` is superseded by this and is no longer used for installation.)

### 3. Install the sample data

* Go to System → Plugins and enable **Sample Data - JED**. Enable **Sample Data - JED Menu** as well if you want a ready-made front-end menu.
* Go to the Home Dashboard and click **Install** next to **JED Sample Data** — this installs sample extensions/reviews/categories/tickets/users. It also moves your admin user to id=5 so you can still log in; the site may log you out afterwards, just log back in as your admin user.
* If you enabled the menu plugin, click **Install** next to **JED Main Menu** afterwards. It creates main-menu items for the browse presets (Top Rated, Most Reviewed, New, Recently Updated, compatibility filters), the abandoned-extensions list and its report form, search, login/registration, extension submission, the developer dashboard and tickets.

### 4. Optional: background tasks

The scheduled routines (link checking, update checking, traffic aggregation, queue worker, privacy pruning, abandonware scanning) are provided by the **Task - JED** plugin. Enable it, then add the tasks you want under System → Scheduled Tasks. None of them are created automatically.

### Migrating an existing JED3 data set

Instead of the sample data, an existing JED3 database can be imported:

* Set the JED3 database name and table prefix in the JED component options (System → Manage → Components → JED). The JED3 database must be on the same server.
* Enable the **Sampledata - JED3 Migration** plugin and run **JED3 Migration** from the Home Dashboard. Each step is a separate request and can be retried on its own.

Running the migration replaces the existing JED data.

### Front-end testing

Sample-data installation creates a test user, **testuserj5final** (password **Who0CaresF0rPasswords**), which all sample front-end data (extensions, reviews, tickets, etc.) is tied to.
