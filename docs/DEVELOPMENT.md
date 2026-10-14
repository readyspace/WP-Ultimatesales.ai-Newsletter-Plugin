# Development

## Structure

- `cleverspeed-newsletter.php`: WordPress hooks, admin credential/mode controls, per-post exclusion and durable worker.
- `includes/config.php`: validated private site settings and verification fingerprint.
- `includes/policy.php`: consent, URL/excerpt/public-page checks and table-based email layout.
- `includes/client.php`: scoped HTTPS provider client, full draft readback and consumed draft/audience attestations.
- `includes/http-policy.php`: pure, configuration-derived route, transport and request-body validation.
- `includes/html-equivalence.php`: complete DOM comparison with narrow provider formatting normalization.
- `includes/credential.php`: authenticated encryption and credential storage.
- `tests/`: standalone synthetic PHP suites plus a separate disposable WordPress/MySQL upgrade laboratory. No real provider requests.

Internal `CleverSpeed\Newsletter`, `cs_*` and `cleverspeed_*` identifiers are retained for migration compatibility. They contain no live account configuration. The public software author is ReadySpace; the newsletter brand comes from each site's configuration.

## Test and package

Run `bash scripts/test.sh` with PHP 8.1+ and required extensions. The script lints PHP and runs ten isolated suites covering policy, worker, credential, admin handlers, configuration, activation, HTTP policy, client integrity, HTML equivalence and isolation configuration. Tests use fictional domains/contacts and mocked HTTP. Do not substitute a live WordPress bootstrap.

Run `bash scripts/package.sh --candidate` to build the isolated-review ZIP from an explicit file list. `--submission` additionally requires approved licence and verified compatibility metadata. Neither build mode replaces the manual release readiness checks. The package uses the published directory slug while preserving legacy data identifiers. Tests, operator documentation, private configuration, Git history and local operational files are excluded; directory readme and an approved licence are included. This plugin has no Composer/npm runtime dependencies, bundled fonts or images. Review both source and archive before releasing.

GitHub Actions runs the isolated suites on PHP 8.1/8.3/8.4 and `scripts/test-wordpress-integration.sh` against released WordPress 6.9/7.1.3 and MySQL 8. The separate integration script refuses database resets outside an explicitly enabled local `rs_newsletter_test` laboratory. It installs the pinned 0.3.3 baseline, preserves seeded options, encrypted credentials, ledger and scheduler through a file upgrade, then exercises real WordPress hooks for standard/headless layouts with/without an optional host restriction. All newsletter HTTP is intercepted with fictional responses. CI does not deploy or use provider credentials or send email. Passing CI does not establish provider schemas, recipient eligibility in a real account or email-client rendering.

## Known limits / next contributions

Configuration currently belongs in private `wp-config.php`; only token entry and sending mode have admin controls. Maintainer-led connection verification is still required. Priorities are a read-only verification wizard, a safely scoped controlled test flow, a configuration UI with reset/reverification, browser-admin security tests, broader host compatibility and clearer reconciliation tools.

Custom post types, multisite, subdirectory sites, template editing, attachments, delivery webhooks and automatic retry after uncertain writes are not supported. The generic client has not been exercised end-to-end against a second provider account.

The campaign API implementation originates from a working single-site pilot. Provider schemas/access may change. Recheck the actual supported endpoints and permissions before altering the client. Never add OAuth/API tokens or private integration records as fixtures.

## Release review

Review consent, duplicate protection, configuration binding, credentials, access/nonce/HTTPS controls, URL restrictions, pagination and error redaction. Run isolated tests, staging admin checks and approved controlled-recipient provider acceptance. Inspect the final ZIP. Record actual results and limitations in release notes, then tag the reviewed commit. Never deploy a release to an existing pilot as an incidental part of publishing source.


## HTTP integration contract

`Client::withHttpIntent()` exposes only bounded unauthenticated article or saved-body reads. API intent remains private to `Client::request()`. `Client::allowsHttp()` is a read-only decision for the current exact synchronous request, including direct call provenance; identical nested HTTP calls and calls outside that scope are rejected. The optional plugin bridge recognizes only the single configured custom host error code and otherwise retains WordPress's result. Do not use it to override generic transport errors or to register an unrestricted domain allowlist.

The request validator is intentionally independent of WordPress hooks. HTML equivalence executes after the HTTP API and response filters complete. Neither helper contains site-specific origins, account identifiers or CMS security behavior. The runtime has no additional installation dependency or must-use entry point.
