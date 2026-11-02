# Security policy

## Supported code

Security fixes target the current `main` branch. There are no supported older release branches at present. Use a maintained PHP/MySQL version and keep your hosting system patched.

## Report a vulnerability privately

Use [GitHub's private vulnerability reporting form](https://github.com/CorentinL5/WDHOTD/security/advisories/new).

Include affected files or URLs, the smallest safe reproduction, expected and observed behavior, impact, and a proposed fix if you have one. Use dummy data. Do not access anyone else's data, run availability tests, send payments or publish secrets.

If the private reporting form is unavailable, open an issue titled **Request for a private security contact** without exploit details. The maintainer will arrange a private channel. Do not attach sensitive reports to a public issue or pull request.

This is a small volunteer-maintained project; there is no guaranteed response time or paid bounty program. Please allow time for a fix before publishing technical details.

## Deployment expectations

- Public routes read generated archives only; database access and imports are CLI-only.
- The sole public write endpoint, `metrics.php`, accepts only bounded same-origin allowlisted counter events. It has no SQL connection. In production its persistent aggregate file must be outside the web root; protect that directory and enforce host-level request limits. Counters are public aggregates and are not authenticated unique-visitor figures.
- Deploy the allowlisted public package described in DEPLOYMENT.md instead of uploading the checkout. Reader backup imports are validated in the browser and never uploaded.
- Keep `.env`, credentials, private settings, logs and personal database contents out of Git and public document roots.
- Protect `.git` and environment files in the web server configuration. Disable directory listing.
- The web worker should have read access to generated archives; only the sync process should publish them. Apply host-level request limits as appropriate.
- Use prepared queries, validated dates, bounded pagination and HTTPS payment/source links.
- Back up the canonical database. Check the scheduled job's status and publish stale cached content rather than triggering work from visits.
- CI has read-only repository permissions and uses pinned third-party actions. Contributions from forks must not receive secrets or privileged deployment permissions.

The project license does not replace the licenses or security requirements of third-party services and content.
