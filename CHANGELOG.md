# Changelog

## 0.3.3 — relative article redirect fix

- Resolve root-relative HTTP 301/308 Location headers against the configured public origin before validating that only the final slash changes.
- Retain rejection of other origins, other paths, query strings, fragments, protocol-relative URLs and redirect chains.
- Add successful relative redirect cases in both slash directions and negative regression cases.
- No changes to settings, encrypted credentials, ledger, consent rules or provider campaign requests.

## 0.3.2 — name and description update

- Approved and published on 11 October 2026 at SVN revision 3739532. The public seven-file package, directory name/version and all four PNG images were verified against the approved release.
- Rename the plugin, administrator screen and directory banner to Newsletter for UltimateSales.AI.
- Explain email notifications, article links, supported WordPress setups and setup needs in plain language. Mark other channels as future plans, not current features.
- Retain the approved WordPress.org slug, plugin filename, text domain, ReadySpace author and every integration/storage identifier.
- No newsletter, consent, credential, scheduling or ledger behavior changes.

## 0.3.1 — WordPress.org first public release

- Replace fictional setup URLs with explicit placeholders; retain the actual service policy links.
- Approved and published on 10 October 2026 at SVN revision 3738304. Public package and all four directory images verified against the reviewed source.

## 0.3.0 — numeric directory submission

- Apply numeric release metadata to the audited implementation and submit the owner-approved GPLv2-or-later package.

## 0.3.0-alpha.3 — WordPress.org readiness review

- Prepare the distinctive ReadySpace Newsletter for UltimateSales.AI name and directory readme, service/data-flow disclosures and separate directory artwork.
- Reject malformed mode requests and require POST for mode/recheck operations.
- Fail closed when the send ledger cannot durably record a transition or the initial archive cannot be excluded.
- Build deterministic allowlisted review packages under the proposed directory slug; preserve legacy options, hooks, ledger and API endpoints.
- ReadySpace approved GPLv2-or-later licensing and confirmed the necessary rights; include the full licence and grant notices.
- ReadySpace approved the linked terms, privacy notice, messaging policy and their URLs; record that approval separately from unfinished public-page details.
- Reuse completed 4 October acceptance for the installed site release; carry forward its corrected native unsubscribe anchor and strict Email DND interpretation, with bounded final-slash handling for headless/conventional article URLs.
- Remains a review candidate: validate changes against the prior acceptance and resolve unfinished terms/privacy details before final submission.

## 0.3.0-alpha.2 — white-label naming

- Use UltimateSales.AI in plugin descriptions, administrator labels, status/error messages, repository documentation and public project title.
- Preserve working API endpoints, storage host checks, credentials, consent rules and queue identifiers.
- No sending-policy or newsletter-template changes. Existing working pilot receives a separate wording-only 0.2.2 patch, not this generic alpha.

## 0.3.0-alpha.1 — initial public source snapshot

- ReadySpace attribution and requested public project name.
- Private per-site configuration replaces the predecessor's fixed customer/account/sender settings.
- Missing configuration fails closed; changed configuration invalidates verification.
- Retains legacy storage identifiers, encrypted administrator credential entry, durable queue, consent checks, draft readback and manual reconciliation for uncertain writes.
- Adds generic installation, operations, development, contribution and security documentation plus synthetic configuration regression tests.
- The working site-specific 0.2.1 pilot is not upgraded by this publication. This generic alpha still needs live staging/provider acceptance and a self-service verification wizard.
