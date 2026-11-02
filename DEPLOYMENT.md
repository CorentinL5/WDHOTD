# Publish the original WDHOTD site

These instructions are for the project owner and authorized deployment of the original project. The contribution-only license does not authorize independent hosted copies.

## What is ready

The public application reads a generated archive and processes no payments, accounts, uploads or database writes. Saved stories and imported backups stay in the reader's browser. The build command creates a small PHP web root and optional ZIP containing only approved public files.

You still need a public domain, a PHP 8.1+ host, a valid TLS certificate and a way to publish updated releases. The local `.test` address and Windows scheduled task do not publish a site to the internet. Search engines also need time to discover and index a domain; a working site is not a promise of traffic.

## Generate archives privately

Keep the repository, MySQL, `.env` and CLI maintenance outside the public document root. Configure `APP_ENV=production` on a production maintenance machine. Generic `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` and `DB_PORT` take priority over environment-specific fields. Production jobs require explicit credentials; they no longer guess the environment from an HTTP host name.

Run the migration if needed, then the bounded sync/export job. Use a least-privilege database account for the job. Visitors need no database account. Back up the canonical database separately; public archives are derived copies.

```sh
php migrate.php
php sync.php --days=7 --warm=3
php configure.php --site-url=https://YOUR_REAL_DOMAIN
php configure.php --support-url=https://revolut.me/corentinl5 --source-url=https://github.com/CorentinL5/WDHOTD
```

`--site-url` accepts a real public HTTPS base URL. Local addresses are rejected as production canonical URLs. Without a public URL, the page requests no indexing.

## Build the public release

```sh
php scripts/package.php --production
```

The command creates a new `dist/release-.../public/` folder, a hash manifest beside it and `wdhotd-public.zip` when PHP's ZIP extension is installed. It excludes `.env`, `.git`, database configuration, SQL dumps, maintenance scripts, tests and logs. Public JSON is projected through an explicit field allowlist again while packaging.

Upload only the contents of `public/`, or extract the generated ZIP into the host's web root. Do not upload the checkout or the entire release directory. Keep the manifest outside the public web root. Keep a known-good release for rollback; switch releases atomically where supported.

For a local preview, omit `--production`. Preview packages disallow indexing even if the private configuration contains a production domain.

## Configure the host

### Apache / compatible PHP hosting

The packaged `.htaccess` disables directory listing, blocks private paths and dotfiles, and redirects HTTP to the configured HTTPS URL in production builds. It requires Apache 2.4 authorization/rewrite support with overrides enabled. Verify that the host honors these rules.

Use the host's certificate provisioning and PHP configuration. Disable `display_errors` and `expose_php` in production. If TLS terminates at a trusted proxy, configure HTTPS handling with the host; an unconfigured proxy can cause redirect loops. Do not trust arbitrary forwarded headers supplied by clients.

### Nginx / PHP-FPM

`deploy/nginx.conf` is a template with a PHP route allowlist, request limits, TLS-only public traffic and no directory listing. Replace the domain, certificate paths, release path and PHP socket, validate the configuration and reload using your normal hosting process. Behind a CDN, configure real client IPs using only trusted provider proxy ranges before applying per-client limits.

The application sends CSP, anti-framing, MIME-sniffing protection, referrer policy and restricted browser permissions. HSTS is sent for securely served requests. Font CSS currently comes from Google Fonts and images from Wikimedia; these origins are explicitly allowed by the policy.

## Verify the actual deployment

- Check the edition, timeline and Saved view on desktop/mobile. Confirm the content policy has not blocked required assets.
- Save a story, reload, export it and import the backup in another browser. Browser storage is scoped to the origin, so moving from `.test` to a public domain also requires export/import.
- Verify HTTP redirects to the real HTTPS domain and its certificate validates normally.
- Verify `/.env`, `/.git/config`, `/config.php`, `/sync.php`, `/tests/` and `/data-cache/` return 403/404 and expose no content.
- Verify robots.txt, sitemap.xml, canonical URLs and response headers. Saved collections request no indexing and are not stored on the server.
- Keep logs private and monitor failed syncs, upstream errors and hosting limits. Cache reduces work; it does not replace hosting capacity or abuse controls.

CI tests generated packages and cache-backed endpoints. Final TLS, server rules and request limits depend on the chosen host and must be verified there.

After publishing, run `php scripts/check-live.php https://YOUR_REAL_DOMAIN` for a small read-only check of the certificate, headers, indexing files and blocked private paths. It never disables certificate verification and does not run a load test or comprehensive security audit. Verify the HTTP-to-HTTPS redirect separately in the selected host configuration.

## Keep it current

Run the private sync daily, build a new production package and publish it with the host's authenticated deployment method. A folder upload does not install a cron job or deployment credentials. The Windows task only refreshes this computer's database/cache and does not upload a release.

## Saved stories and recovery

Readers can save up to 300 stories and export/import a versioned JSON backup of up to 2 MB. Backups retain event text and sources, independent of database IDs or archive availability. Imports validate the entire file before merging, preserve existing stories, discard unapproved link/media origins and never upload the file. Images remain external source URLs and need a network connection.

Clearing browser storage, private browsing, switching devices or changing domains can lose local favorites without a backup. There is no account-based or automatic cloud recovery. The Saved view explains this and offers backup controls next to the collection.
