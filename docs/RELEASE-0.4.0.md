# Release 0.4.0 — 15 October 2026

Published to the existing WordPress.org listing as SVN revision `3747310`, using the `readyspace` committer account. The official versioned ZIP and exported SVN tag contain the same nine runtime/readme/licence files as the reviewed submission package. No live CMS plugin files, configuration, must-use files or newsletter jobs were changed by this release task.

## Behavior

The regular plugin now contains configuration-derived HTTP validation and complete saved-email equivalence checking. Scheduling requires the same client to have verified the exact draft and a fresh, complete eligible audience within 60 seconds. The schedule freezes those recipient IDs and consumes its authorization before transport, preventing direct bypass and replay after an uncertain result.

An exact synchronous request scope prevents nested requests or changed transport arguments from borrowing newsletter authorization. Article/storage reads retain WordPress safe-URL validation and carry no CRM token. Standard and headless installations use the same code with their own CMS/public origins. Ordinary installations require no new setting and preserve the existing configuration fingerprint. Hosts with explicit outbound restrictions can opt into the documented single-error-code integration, which requires fresh acceptance.

Settings, encrypted credentials, queue identifiers, archive exclusions, ledger and scheduler retain their existing identifiers. There is no archive catch-up, draft promotion or automatic must-use removal. Previews, redirects, WordPress mail, indexing and other CMS protections remain outside this plugin's scope.

## Validation

[Reviewed CI run](https://github.com/readyspace/WP-Ultimatesales.ai-Newsletter-Plugin/actions/runs/38651785953), source commit `70bb53aa12a4fed57d347c00f26ddd2a392797b2`:

- Lint and 1,023 isolated assertions on each of PHP 8.1, 8.3 and 8.4.
- WordPress 6.9 and 7.1.3 with MySQL 8 on each PHP version: 24 complete profiles, 282 assertions per profile, 6,768 assertions total. Official core checksums verified before installation.
- Every profile upgrades the actual published 0.3.3 files, preserves encrypted credentials/settings/history/cron, and checks standard/headless layouts with and without host isolation.
- Real WordPress hooks exercise full draft verification, frozen audiences, nested requests, argument mutation, unsafe URL flags, failed/uncertain outcomes and replay protection. All provider HTTP is intercepted with fictional fixtures; no real campaigns or emails are sent.
- Official Plugin Check full report: zero errors, no database diagnostics, 28 visible reviewed warnings. No checker-code or shipped-file exclusions.

The remaining warnings are ten direct-query and ten no-cache findings for the durable ledger and advisory locks, seven retained namespace warnings, and one backtrace warning. Ledger reads deliberately use current database state; adding an object cache could allow duplicate processing. Legacy identifiers preserve upgrades. The bounded backtrace omits arguments and performs request provenance validation without logging or exposing credentials.

The checker laboratory provisions only an empty copy of the ledger schema under its temporary prefix, addressing its [documented custom-table setup limitation](https://github.com/WordPress/plugin-check/issues/1127). Production code does not create a replacement ledger in response to that checker behavior. The full report parser and integration completion checks fail CI on actual errors or incomplete tests.

## Package proof and deployment limits

- Reviewed submission ZIP: 36,729 bytes, SHA-256 `d8f6f699abf3c03d59a8a45157e4570869a46d08a149b137ac78a6ca366bf922`.
- Official WordPress.org ZIP: 37,085 bytes, SHA-256 `f2bcdaa222fb642a417eb23b2a8798ecb06d5dd5b1363a5a8184cebb70bdd130`.
- Different ZIP container metadata; all nine file hashes match exactly.

[Official download](https://downloads.wordpress.org/plugin/readyspace-newsletter-for-ultimatesales-ai.0.4.0.zip). WordPress.org applies its [release security review and six-hour update cooldown](https://make.wordpress.org/plugins/2026/09/09/automated-security-review-for-plugin-releases/) before dashboard update distribution.

Real account delivery, unsubscribe suppression and each host's private configuration still require controlled acceptance. Sites with version-pinned newsletter adapters must use the [migration procedure](INSTALLATION.md) before removing those files. Production migration is a separate task. The temporary SVN credential was deleted after the successful publishing attempt.
