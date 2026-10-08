# WordPress.org release readiness

Audit date: 8 October 2026. Base: `6c61cb991ee46b8c9d0c9698de83c368bfbbcd85`.

**Status: owner-approved submission candidate; directory approval is pending.** ReadySpace authorized GPLv2-or-later licensing and confirmed the necessary rights on 8 October 2026. The owner subsequently approved the name/slug, directory acknowledgements and upload. The account's required 2FA is verified. Version `0.3.0` applies WordPress.org's numeric metadata requirement to the audited `0.3.0-alpha.3` implementation; integration behavior is unchanged. Passing tests does not constitute directory approval or universal installation acceptance.

## Required release gates

- [x] ReadySpace authorized GPLv2-or-later licensing and confirmed the necessary rights on 8 October 2026. Full licence texts are included at repository root and `cleverspeed-newsletter/LICENSE`; PHP/readme fields, contribution guidance and original artwork grants agree. See `COPYRIGHT.txt`.
- [x] ReadySpace's owner approved the linked UltimateSales.AI terms, privacy notice, messaging policy and their URLs on 8 October 2026. See `SERVICE-POLICY-APPROVAL.md`; no further approval of those drafts is requested.
- [x] Owner confirmed ReadySpace Network Pte Ltd, 531 Upper Cross Street #03-11, Singapore 050531, and `contact@readyspace.com` for privacy requests/complaints. The contact is staged in the website patch; the public page is unchanged.
- [x] The readme identifies the external SaaS, data sent/read, authorisation, local retention and limits of click/UTM tracking flags, and links the approved policies. WordPress.org guidelines 6/7 require clear service/data-flow disclosures; they do not prescribe retention periods or require completion of every SaaS contractual provision as submission fields.
- [x] Reuse completed 4 October real-provider acceptance for the installed `0.3.0-alpha.2-usai.2` site release: controlled delivery, corrected custom unsubscribe, native suppression, final installed-policy exclusion, configuration/scopes and actual consent-tag/DND workflow checks. Owner confirmed completion on 8 October. See `ACCEPTANCE-BRIDGE.md`; a new account or repeat positive mail is not required solely for directory packaging.
- [x] Reuse the completed real WordPress/MySQL suite (53 assertions plus install/live-state checks), independent-connection advisory locking and reviewed upgrade/ledger preservation. These establish the retained host mechanisms, not an execution of every new alpha.3 code path on that host.
- [x] Changed-code validation passed: 293 synthetic assertions and 13-file syntax checks on PHP 8.1/8.3/8.4. The numeric release has fresh syntax checks on all five packaged PHP files on each version; functional evidence is reused after independently proving only two header/display text replacements. Original functional checks include including the provider-contract corrections, bounded canonical/redirect handling and ledger/archive/admin safeguards. These are isolated checks, distinct from the completed 4 October host/provider acceptance.
- [x] Official Plugin Check 2.1.0 rerun on the licensed ZIP (SHA-256 `c54e7e8b9ee969367be624bbdedaae0a8a45de0d648caf84a2bfd09e5b028d51`): 29 static and 34 including runtime checks, zero errors and 25 documented ledger/cache/namespace warnings. Exact ZIP passes activation/default-Off/admin/cron checks on WordPress 6.9 and 7.1.3.
- [x] Reuse the completed actual-host HTTPS/admin, encrypted credential/configuration verification, normal cache and mode-save/final-audit checks. The alpha.3 administrator type/POST safeguards have separate isolated regression coverage; no live credential is copied or rotated in this submission review.
- [x] Verify ReadySpace publisher ownership and required 2FA. The signed-in `readyspace` company account and enabled 2FA were verified. The owner completed authentication setup personally; no setup secret or backup code was accessed. Any other committers need individual secured accounts.
- [x] Owner approved **ReadySpace Newsletter for UltimateSales.AI** and proposed slug `readyspace-newsletter-for-ultimatesales-ai`. Search found no exact match, but availability and final approval are determined by WordPress.org. The slug becomes permanent after approval.
- [x] Owner explicitly approved submission and all eight directory acknowledgements after reviewing the ZIP and results. The upload form initially rejected the alpha suffix before accepting a submission. The necessary numeric-version correction changes metadata/display text only, within that approved submission's scope; no new data, permissions, terms or integration behavior are introduced.

## Preserved integration and migration boundaries

The API host, campaign endpoints, v3 campaign version, consent tags, send payload, newsletter template, encrypted token format, `cs_*` options/post metadata/table, and `cleverspeed_newsletter_tick` retain their existing identities. No production site is deployed or migrated during this review.

The source folder remains `cleverspeed-newsletter`; directory packages use the proposed slug. On a pilot migration, first set sending Off, stop the scoped scheduler and allow in-flight work to settle. Back up privately, deactivate the existing copy, install the reviewed directory copy and preserve all data/credentials. Never activate both folders: duplicate registered callbacks would undermine operational assumptions. Reverify configuration before re-enabling delivery. No archive replay or automatic promotion of old drafts is introduced.

## Service-policy follow-up, separate from directory submission

At the owner's request, a concrete proposed privacy notice is staged in the isolated website branch and supplied as `PRIVACY-NOTICE-PROPOSED.txt` in the handoff. It uses the confirmed operator/address/contact, purpose-based retention with limited opt-out records, access/correction/request handling and accurate distinctions between email opens, click/UTM flags and website analytics. These new operating commitments are proposed, not claimed to be adopted or deployed. Existing policy approvals remain recorded.

Before publishing that website update, align the proposed retention/request workflow and transfer safeguards with actual operations. Confirm the LC Email Open setting if the notice is to state a definite setting; the current proposal discloses possible open tracking without claiming it is off. Website Analytics preferences control Google Analytics, not all HighLevel tracking. Contract-specific terms are separate service maintenance. These tasks are not additional standard WordPress.org upload-form gates, and completed delivery/unsubscribe/host checks need not be repeated for this drafting work.

## Packaging

`bash scripts/package.sh --candidate` produces a deterministic, explicit-allowlist ZIP marked `REVIEW-ONLY` and its SHA-256. `--submission` also requires a numeric version, matching Stable tag, approved GPLv2-or-later fields/licence and tested compatibility metadata. The script is not a substitute for owner authorisation, validation and directory review.

The package includes PHP runtime files, `readme.txt` and the approved licence if present. It excludes Git history, tests, private `wp-config.php`, logs, credentials, contact fixtures and directory artwork. Assets are maintained separately for SVN `assets/` after approval. No destructive uninstall is added: the send ledger remains to prevent duplicate sends.

## Workflow after all gates pass

On the signed-in Add your Plugin page, select the reviewed ZIP (under 10 MB), add the prepared service/data-flow summary, then check only truthful acknowledgements. Obtain final approval before clicking Upload. Capture the receipt/slug and await the review email; submission is not directory publication. After approval, use the assigned SVN repository for the same verified release and directory assets; make the first public SVN release a separate explicitly approved action.

References: [directory guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/), [Plugin Check](https://wordpress.org/plugins/plugin-check/), [readme rules](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/), [required 2FA and checks](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/).

## Appendix: database and naming warnings

The custom `cs_newsletter_jobs` table is a durable send ledger, rather than ordinary cached display data. Prepared queries read current job state, claim MySQL/MariaDB advisory locks and persist state before mutating campaign requests. The queue selects at most three jobs per tick; the administrator view selects at most 30. Queue/lock results deliberately bypass an object cache because another worker can change their state. `DirectQuery` and `NoCaching` warnings therefore remain visible for reviewer assessment; no blanket checker suppression has been added.

Runtime query identifiers use WordPress `%i` placeholders, with values bound separately. Activation uses `dbDelta()` for the declared schema and verifies that the table exists and the archive exclusion query succeeds before marking installation or scheduling the worker. The schema's table identifier comes only from WordPress's database prefix plus the fixed `cs_newsletter_jobs` suffix, and its collation comes from `$wpdb->get_charset_collate()`. Its activation schema inspection/change can still produce a `SchemaChange` warning. These operations preserve the ledger and do not delete existing records.

The existing `CleverSpeed\Newsletter` namespace and `cs_*` identifiers are retained for compatibility with the earlier pilot. Namespace-prefix warnings must be reviewed with that migration constraint in mind; changing the public plugin name/ZIP folder does not rename its database or hook identifiers. These explanations support reviewer assessment; the remaining publisher/name/public-submission gates above still apply.
