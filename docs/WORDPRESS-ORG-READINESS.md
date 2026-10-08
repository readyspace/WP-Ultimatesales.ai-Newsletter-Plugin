# WordPress.org release readiness

Audit date: 8 October 2026. Base: `6c61cb991ee46b8c9d0c9698de83c368bfbbcd85`.

**Status: review candidate only. Do not upload this alpha while the gates below are unresolved.** Source publication, passing unit tests and generating a ZIP do not establish distribution rights or production acceptance.

## Required release gates

- [ ] ReadySpace approves a GPL-compatible licence and confirms rights/provenance for all included code and artwork. The repository currently grants no redistribution licence. GPLv2-or-later is proposed; do not add a licence grant without owner authorization. After approval add the full licence to `cleverspeed-newsletter/LICENSE`, matching PHP/readme licence fields, and revise the root licence/contribution wording.
- [ ] Owner approves final UltimateSales.AI service terms, privacy notice and messaging policy. At audit time `https://ultimatesales.ai/terms`, `/privacy` and `/messaging-policy` each say they are drafts for review before production launch. Confirm operator/privacy contact, processors/transfers, retention and actual service/API terms. Do not silently treat draft text as final.
- [ ] Generic alpha acceptance on a separate controlled provider account: location/staff identity; least-privilege scopes; contacts/search DND fields; draft creation/readback; controlled-recipient delivery/rendering; actual unsubscribe suppression; correct confirmed/pending/unsubscribed tag mapping; final refresh of consent. Record redacted outcomes, not credentials, subscriber details or full API responses. The original pilot is not this generic alpha.
- [ ] Staging with the actual host's MySQL/MariaDB: successful `GET_LOCK`/`RELEASE_LOCK`, installation/archive seed, concurrent workers, durable state writes, upgrade and recovery. Playground's SQLite tests do not establish advisory-lock compatibility.
- [ ] Official Plugin Check rerun on the exact final ZIP; resolve errors and document any independently justified false positives. Complete real WordPress admin checks (capability, nonce, HTTPS credential, exclusion and mode handling). Use only versions actually tested in the headers/readme.
- [ ] Confirm ReadySpace publisher ownership and required 2FA. The signed-in account was verified as `readyspace`; 2FA state has not been verified or altered. Any other committers need individual secured accounts.
- [ ] Approve the proposed public name **ReadySpace Newsletter for UltimateSales.AI** and candidate slug `readyspace-newsletter-for-ultimatesales-ai`. Search found no exact match, but availability and final approval are determined by WordPress.org. The slug becomes permanent after approval.
- [ ] Owner explicitly approves the final public submission and directory acknowledgements after reviewing the exact ZIP and results.

## Preserved integration and migration boundaries

The API host, campaign endpoints, v3 campaign version, consent tags, send payload, newsletter template, encrypted token format, `cs_*` options/post metadata/table, and `cleverspeed_newsletter_tick` retain their existing identities. No production site is deployed or migrated during this review.

The source folder remains `cleverspeed-newsletter`; directory packages use the proposed slug. On a pilot migration, first set sending Off, stop the scoped scheduler and allow in-flight work to settle. Back up privately, deactivate the existing copy, install the reviewed directory copy and preserve all data/credentials. Never activate both folders: duplicate registered callbacks would undermine operational assumptions. Reverify configuration before re-enabling delivery. No archive replay or automatic promotion of old drafts is introduced.

## Packaging

`bash scripts/package.sh --candidate` produces a deterministic, explicit-allowlist ZIP marked `REVIEW-ONLY` and its SHA-256. It is suitable only for isolated review. `--submission` refuses missing approved GPLv2-or-later fields/licence or tested compatibility metadata; the script is not a substitute for the release gates above.

The package includes PHP runtime files, `readme.txt` and the approved licence if present. It excludes Git history, tests, private `wp-config.php`, logs, credentials, contact fixtures and directory artwork. Assets are maintained separately for SVN `assets/` after approval. No destructive uninstall is added: the send ledger remains to prevent duplicate sends.

## Workflow after all gates pass

On the signed-in Add your Plugin page, select the reviewed ZIP (under 10 MB), add the prepared service/data-flow summary, then check only truthful acknowledgements. Obtain final approval before clicking Upload. Capture the receipt/slug and await the review email; submission is not directory publication. After approval, use the assigned SVN repository for the same verified release and directory assets; make the first public SVN release a separate explicitly approved action.

References: [directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/), [Plugin Check](https://wordpress.org/plugins/plugin-check/), [readme rules](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/), [required 2FA and checks](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/).

## Appendix: database and naming warnings

The custom `cs_newsletter_jobs` table is a durable send ledger, rather than ordinary cached display data. Prepared queries read current job state, claim MySQL/MariaDB advisory locks and persist state before mutating campaign requests. The queue selects at most three jobs per tick; the administrator view selects at most 30. Queue/lock results deliberately bypass an object cache because another worker can change their state. `DirectQuery` and `NoCaching` warnings therefore remain visible for reviewer assessment; no blanket checker suppression has been added.

Runtime query identifiers use WordPress `%i` placeholders, with values bound separately. Activation uses `dbDelta()` for the declared schema and verifies that the table exists and the archive exclusion query succeeds before marking installation or scheduling the worker. The schema's table identifier comes only from WordPress's database prefix plus the fixed `cs_newsletter_jobs` suffix, and its collation comes from `$wpdb->get_charset_collate()`. Its activation schema inspection/change can still produce a `SchemaChange` warning. These operations preserve the ledger and do not delete existing records.

The existing `CleverSpeed\Newsletter` namespace and `cs_*` identifiers are retained for compatibility with the earlier pilot. Namespace-prefix warnings must be reviewed with that migration constraint in mind; changing the public plugin name/ZIP folder does not rename its database or hook identifiers. These explanations do not resolve the licence, provider acceptance, actual-host locking or public-submission gates above.
