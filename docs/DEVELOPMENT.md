# Development

## Structure

- `cleverspeed-newsletter.php`: WordPress hooks, admin credential/mode controls, per-post exclusion and durable worker.
- `includes/config.php`: validated private site settings and verification fingerprint.
- `includes/policy.php`: consent, URL/excerpt/public-page checks and table-based email layout.
- `includes/client.php`: fixed HTTPS provider client and campaign readback.
- `includes/credential.php`: authenticated encryption and credential storage.
- `tests/`: standalone synthetic PHP tests. No WordPress bootstrap, real database or provider requests.

Internal `CleverSpeed\Newsletter`, `cs_*` and `cleverspeed_*` identifiers are retained for migration compatibility. They contain no live account configuration. The public software author is ReadySpace; the newsletter brand comes from each site's configuration.

## Test and package

Run `bash scripts/test.sh` with PHP 8.1+ and required extensions. The script lints PHP and runs five isolated suites covering policy, worker, credential, admin handlers and configuration. Tests use fictional domains/contacts and mocked HTTP. Do not substitute a live WordPress bootstrap.

Run `bash scripts/package.sh` to build the installable ZIP from an explicit file list. Tests, documentation, configuration, Git history and local operational files are excluded. This alpha has no Composer/npm runtime dependencies, bundled fonts or images. Review both source and archive before releasing.

GitHub Actions runs isolated checks only. It does not deploy, obtain provider credentials, create campaigns or send email. Passing CI does not prove browser-admin security, concurrent database behaviour, provider schemas, recipient eligibility in a real account or email-client rendering.

## Known limits / next contributions

Configuration currently belongs in private `wp-config.php`; only token entry and sending mode have admin controls. Maintainer-led connection verification is still required. Priorities are a read-only verification wizard, a safely scoped controlled test flow, a configuration UI with reset/reverification, browser-admin security tests, a tested WordPress/PHP matrix and clearer reconciliation tools.

Custom post types, multisite, subdirectory sites, template editing, attachments, delivery webhooks and automatic retry after uncertain writes are not supported. The generic alpha has not been exercised end-to-end against a second provider account.

The campaign API implementation originates from a working single-site pilot. Provider schemas/access may change. Recheck the actual supported endpoints and permissions before altering the client. Never add OAuth/API tokens or private integration records as fixtures.

## Release review

Review consent, duplicate protection, configuration binding, credentials, access/nonce/HTTPS controls, URL restrictions, pagination and error redaction. Run isolated tests, staging admin checks and approved controlled-recipient provider acceptance. Inspect the final ZIP. Record actual results and limitations in release notes, then tag the reviewed commit. Never deploy a release to an existing pilot as an incidental part of publishing source.
