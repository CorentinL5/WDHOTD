# Develop with us

WDHOTD welcomes improvements to the original project through pull requests. It is source visible for review and contribution; it is not licensed for independent deployments or republication. See [LICENSE](LICENSE) and [CONTRIBUTING.md](CONTRIBUTING.md).

## Stack and layout

PHP 8.1+ is the tested baseline. Use MySQL 8.x or a compatible MariaDB, the `pdo_mysql` and `curl` extensions, and Node.js for the JavaScript syntax check. No Composer, npm installation or framework build is required.

- `index.php`, `assets/`: English reading experience and timeline.
- `feed.php`, `stats.php`, `calendar.php`, `sponsor.php`: public reads of generated JSON; no database connection.
- `import_service.php`, `images.php`, `history.php`: import and source interpretation.
- `sync.php`, `export.php`, `configure.php`: CLI maintenance and publication.
- `data-cache/`: generated and ignored. MySQL is the authoritative store.

## Normal local development

1. Copy `env.example.txt` to `.env` and fill in your own local database credentials.
2. Create/import the schema in `db_wdhotd.sql` for a new database.
3. Run `php migrate.php`, then `php sync.php --days=1 --warm=0` and `php export.php`.
4. Start `php -S 127.0.0.1:8000` and open the local URL. Keep previews local to work on your contribution.

Use `php refresh_images.php` followed by `php export.php` after changing image selection. Run a manual import with `php import.php --month=11 --day=2`; use `--force` only when needed. Visitors must never initiate imports.

## Offline test fixtures

CI uses a dedicated database named **wdhotd_test** and synthetic stories, so checks do not call Wikipedia. The fixture seeder refuses any other database name. Configure `.env` for that database, then run:

```sh
php tests/seed.php
php migrate.php
php export.php
php -S 127.0.0.1:8000
```

In another terminal:

```sh
php tests/images.php
php tests/metrics.php
php tests/smoke.php http://127.0.0.1:8000
node --check assets/js/feed.js
node --test tests/collection.test.cjs
php tests/package.php
```

Check PHP syntax for changed files with `php -l path/to/file.php`. The CI workflow runs syntax checks, the image rules and actual HTTP/cache tests on PHP 8.1 and 8.4.

## Visual checks

Check the daily edition, discovery and timeline on narrow and wide screens. Verify image proportions, missing-image fallback, contrast, keyboard focus, Escape to close details, era/year navigation, reversed chronology, empty search and an unpublished date. Respect reduced-motion preferences.

## Boundaries that matter

No database or import credentials in public routes. No visitor accounts, private data collection or payment APIs. Support links open the provider directly. Do not expose CLI scripts over HTTP. Cache exports must use an explicit public-field allowlist and publish complete files atomically.

The source repository and its CI are the collaboration path. Do not publish an independent hosted preview, copied product, package or repository outside the contribution workflow without written permission.
