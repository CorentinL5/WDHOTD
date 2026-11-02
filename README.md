# WDHOTD — What Did Happened On This Day

A free English-language history journal. The code is **source visible for evaluation and contributions to this original repository**, under the limited permission in [LICENSE](LICENSE).

You may inspect the code, test changes locally, and propose improvements through pull requests here. The permission does **not** authorize independent deployments, republication, sale or monetized copies. Public GitHub repositories can still be viewed, downloaded and forked within GitHub's terms; this policy is not a technical barrier to copying. This is not OSI-defined open source. Third-party materials keep their own licenses, and rights already granted to older versions are not revoked.

[Develop with us](DEV_WITH_US.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md) · [Community guidelines](CODE_OF_CONDUCT.md)

[Deployment and recovery guide](DEPLOYMENT.md)

## Support the original project

If WDHOTD makes your day a little more interesting, you can [support the project through Revolut](https://revolut.me/corentinl5). Support is voluntary; every story stays free. Contributions to the code belong in [CorentinL5/WDHOTD](https://github.com/CorentinL5/WDHOTD).

## Experience

- A daily edition with a lead story and a selection spread across centuries.
- One-story-at-a-time discovery with the actual event and its article context.
- An illustrated timeline, era navigation, a proportional year overview, year gaps and oldest/newest-first controls. Reading cards are not to scale.
- Date exploration, events/births/deaths/traditions, search and a **Surprise me** button that picks a published date.
- English interface, responsive layouts, keyboard navigation and framed high-resolution images.
- Saved stories with browser-local storage and validated JSON export/import for recovery across browsers or devices. There is no automatic cloud synchronization.

No accounts, subscriptions, Premium tiers or email collection. Every story stays free. Support is optional through the configured external payment page. WDHOTD processes no payments and stores no payment details.

## Installation

PHP 8.1+ with `pdo_mysql` and `curl`, MySQL/MariaDB, and a web server. The documented local setup is for evaluating or contributing to the original project within LICENSE.

1. Copy `env.example.txt` to `.env` and set the database credentials.
2. For a new database, import `db_wdhotd.sql` (existing tables are preserved).
3. Run `php migrate.php` to add the editorial fields and configuration tables.
4. Run `php sync.php --days=7 --warm=3` to import upcoming dates and gradually fill the archive.
5. Serve the project through the configured virtual host, or `php -S localhost:8000`.

Local Laragon URL: [https://wdhotd.test:8443](https://wdhotd.test:8443). Direct timeline URL: `/?view=timeline`.

## Visitors never connect to MySQL

MySQL remains the authoritative store for stories, source metadata, import history, project settings and optional advertisements. A CLI job generates sanitized public JSON files in the ignored `data-cache/` directory. Public pages and APIs only read those generated files; they never connect to MySQL or Wikipedia, record visits, or start an import. Public reading creates no session cookie.

`feed.php` caps pages at 100 items and offsets at 10,000. JSON responses provide ETags, HTTP 304 and a five-minute public cache lifetime. These reduce repeated transfer and server work; hosting-level request limits are still separate from application caching.

Keep `data-cache/` writable by the sync job and readable by the web server. Visitors can only select a date/category/page from published data. Cache files contain no credentials, private settings or raw source JSON. Source image files continue to load from Wikimedia.

An unpublished date returns an empty archive and never triggers work. **Surprise me** chooses from `calendar.php`, so it only opens dates already published.

## Bounded scheduled imports

`php sync.php --days=7 --warm=3` refreshes today plus the next six days, and at most three missing dates. That is at most ten upstream requests per run (fewer when already fresh). Options are capped at 14 upcoming dates and seven missing dates. A global MySQL advisory lock prevents concurrent syncs. Individual imports have their own lock, retry cooldown and transaction. Successful cached data survives upstream failures.

The local Windows task can be installed or updated with:

```powershell
./scripts/install-local-task.ps1
```

It runs daily at **00:15 in the Windows timezone**, starts later if missed, requires network availability and an interactive user session, ignores overlapping runs, and has a 15-minute execution limit. Laragon/MySQL must be available. For a production Linux host, schedule the same PHP command using that host's scheduler; the Windows task only runs on this computer.

Useful CLI commands:

```sh
php import.php --month=11 --day=2
php import.php --month=11 --day=2 --force
php export.php
php refresh_images.php
php export.php
```

The import, export, settings, migration and image-refresh scripts reject HTTP requests. `refresh_images.php` recalculates stored image choices; run `export.php` afterwards to publish them.

## Voluntary support

Configure a valid HTTPS link, stored in `AppSetting` and published with the archive:

```sh
php configure.php --support-url=https://your-payment-page.example
php configure.php --source-url=https://your-repository.example
```

An empty support URL hides the support button. The link opens the provider's own page; there are no payment APIs, webhooks, billing tables or secrets in the site. A supporter does not unlock content.

## Optional small advertisements

Campaigns remain in `Advertisement`, then are published by the CLI job. Set a unique `slug`, `kind = 'sponsor'`, copy, an HTTPS destination, `link_label`, `active = 1` and optional start/end times and priority. Run `php export.php`. The strip is labeled “Advertisement” and uses `rel="sponsored noopener noreferrer"`. No tracking network is installed. With no active campaigns it stays hidden. The old Premium promotion is disabled.

## API and storage

- `GET /feed.php?month=11&day=2&type=all&offset=0&limit=20`: cached stories and pagination.
- `GET /stats.php?month=11&day=2`: cached counts and year range.
- `GET /calendar.php`: available dates.
- `GET /sponsor.php`: active published advertisement or `null`.

Canonical tables are `OTDItem`, `OTDImport`, `AppSetting` and `Advertisement`. Legacy visit-log or launch-list tables are left intact during migration but are no longer used or exposed by the app.

Images and their dimensions are stored in source metadata (`_image`). Up to 960-pixel Wikimedia derivatives are used when available. Generic flags/logos and inadequate source images are excluded; portraits and archive documents are framed. Wikipedia supplies the facts; daily story selection is automatic.

## Checks

```sh
php tests/images.php
php tests/smoke.php http://127.0.0.1:8000
node --check assets/js/feed.js
node --test tests/collection.test.cjs
php tests/package.php
```

The smoke test checks actual cached responses, ETags, limits, navigation data and rejection of public write routes. It collects no email, sends no payment and performs no imports.

## Licenses and attribution

The current application code uses the [WDHOTD source-visible, contribution-only permission](LICENSE), with copyright information in [NOTICE](NOTICE). This does not replace the licenses of Wikipedia text, source media or external fonts. Stories link to their Wikipedia sources; see [Wikipedia's copyright information](https://en.wikipedia.org/wiki/Wikipedia:Copyrights).
