# WordPress.org release readiness

Audit date: 8 October 2026. Base: `6c61cb991ee46b8c9d0c9698de83c368bfbbcd85`.

**Status: review candidate only. Do not upload this alpha while the gates below are unresolved.** ReadySpace authorized GPLv2-or-later licensing and confirmed the necessary rights on 8 October 2026. Passing tests and generating a ZIP do not establish production acceptance.

## Required release gates

- [x] ReadySpace authorized GPLv2-or-later licensing and confirmed the necessary rights on 8 October 2026. Full licence texts are included at repository root and `cleverspeed-newsletter/LICENSE`; PHP/readme fields, contribution guidance and original artwork grants agree. See `COPYRIGHT.txt`.
- [x] ReadySpace's owner approved the linked UltimateSales.AI terms, privacy notice, messaging policy and their URLs on 8 October 2026. See `SERVICE-POLICY-APPROVAL.md`; no further approval of those drafts is requested.
- [ ] Complete the factual details still deferred by the public terms/privacy pages. The browser-verified privacy page already identifies ReadySpace Network Pte Ltd and the newsletter's GoHighLevel/LeadConnector flow. Privacy contact, actual retention and transfer safeguards, rights-request handling and applicable contractual specifics remain unfinished. A draft label alone does not impose a WordPress.org approval gate; the approved text must accurately describe the production service.
- [ ] Generic alpha acceptance on a separate controlled provider account: location/staff identity; least-privilege scopes; contacts/search DND fields; draft creation/readback; controlled-recipient delivery/rendering; actual unsubscribe suppression; correct confirmed/pending/unsubscribed tag mapping; final refresh of consent. Record redacted outcomes, not credentials, subscriber details or full API responses. The original pilot is not this generic alpha.
- [ ] Staging with the actual host's MySQL/MariaDB: successful `GET_LOCK`/`RELEASE_LOCK`, installation/archive seed, concurrent workers, durable state writes, upgrade and recovery. Playground's SQLite tests do not establish advisory-lock compatibility.
- [x] Official Plugin Check 2.1.0 rerun on the licensed ZIP (SHA-256 `41374963902989e9e434b4eacb6ef656aab27f8c05835bbdbcb627d6a9e86388`): 29 static and 34 including runtime checks, zero errors and 25 documented ledger/cache/namespace warnings. Exact ZIP passes activation/default-Off/admin/cron checks on WordPress 6.9 and 7.1.3.
- [ ] Complete staging WordPress admin checks with the actual HTTPS host (capability, nonce, credential replacement/rotation, exclusion and mode handling). The isolated handler tests are mocked and the WordPress Playground tests do not establish all actual-host behaviours.
- [ ] Confirm ReadySpace publisher ownership and required 2FA. The signed-in account was verified as `readyspace`; 2FA state has not been verified or altered. Any other committers need individual secured accounts.
- [ ] Approve the proposed public name **ReadySpace Newsletter for UltimateSales.AI** and candidate slug `readyspace-newsletter-for-ultimatesales-ai`. Search found no exact match, but availability and final approval are determined by WordPress.org. The slug becomes permanent after approval.
- [ ] Owner explicitly approves the final public submission and directory acknowledgements after reviewing the exact ZIP and results.

## Preserved integration and migration boundaries

The API host, campaign endpoints, v3 campaign version, consent tags, send payload, newsletter template, encrypted token format, `cs_*` options/post metadata/table, and `cleverspeed_newsletter_tick` retain their existing identities. No production site is deployed or migrated during this review.

The source folder remains `cleverspeed-newsletter`; directory packages use the proposed slug. On a pilot migration, first set sending Off, stop the scoped scheduler and allow in-flight work to settle. Back up privately, deactivate the existing copy, install the reviewed directory copy and preserve all data/credentials. Never activate both folders: duplicate registered callbacks would undermine operational assumptions. Reverify configuration before re-enabling delivery. No archive replay or automatic promotion of old drafts is introduced.

## Packaging

`bash scripts/package.sh --candidate` produces a deterministic, explicit-allowlist ZIP marked `REVIEW-ONLY` and its SHA-256. It is prepared for isolated review; the label records release-validation status while GPLv2-or-later rights apply. `--submission` refuses missing approved GPLv2-or-later fields/licence or tested compatibility metadata; the script is not a substitute for the release gates above.

The package includes PHP runtime files, `readme.txt` and the approved licence if present. It excludes Git history, tests, private `wp-config.php`, logs, credentials, contact fixtures and directory artwork. Assets are maintained separately for SVN `assets/` after approval. No destructive uninstall is added: the send ledger remains to prevent duplicate sends.

## Workflow after all gates pass

On the signed-in Add your Plugin page, select the reviewed ZIP (under 10 MB), add the prepared service/data-flow summary, then check only truthful acknowledgements. Obtain final approval before clicking Upload. Capture the receipt/slug and await the review email; submission is not directory publication. After approval, use the assigned SVN repository for the same verified release and directory assets; make the first public SVN release a separate explicitly approved action.

References: [directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/), [Plugin Check](https://wordpress.org/plugins/plugin-check/), [readme rules](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/), [required 2FA and checks](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/).

## Appendix: database and naming warnings

The custom `cs_newsletter_jobs` table is a durable send ledger, rather than ordinary cached display data. Prepared queries read current job state, claim MySQL/MariaDB advisory locks and persist state before mutating campaign requests. The queue selects at most three jobs per tick; the administrator view selects at most 30. Queue/lock results deliberately bypass an object cache because another worker can change their state. `DirectQuery` and `NoCaching` warnings therefore remain visible for reviewer assessment; no blanket checker suppression has been added.

Runtime query identifiers use WordPress `%i` placeholders, with values bound separately. Activation uses `dbDelta()` for the declared schema and verifies that the table exists and the archive exclusion query succeeds before marking installation or scheduling the worker. The schema's table identifier comes only from WordPress's database prefix plus the fixed `cs_newsletter_jobs` suffix, and its collation comes from `$wpdb->get_charset_collate()`. Its activation schema inspection/change can still produce a `SchemaChange` warning. These operations preserve the ledger and do not delete existing records.

The existing `CleverSpeed\Newsletter` namespace and `cs_*` identifiers are retained for compatibility with the earlier pilot. Namespace-prefix warnings must be reviewed with that migration constraint in mind; changing the public plugin name/ZIP folder does not rename its database or hook identifiers. These explanations do not resolve the provider acceptance, actual-host locking or public-submission gates above.
