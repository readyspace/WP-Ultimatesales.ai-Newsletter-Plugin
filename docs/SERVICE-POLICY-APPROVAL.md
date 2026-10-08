# Service policy approval

ReadySpace's owner confirmed on 8 October 2026 that they read and approve the referenced service policies and their URLs:

- https://ultimatesales.ai/terms
- https://ultimatesales.ai/privacy
- https://ultimatesales.ai/messaging-policy

This completes the owner-review gate for these drafts. It is separate from the previously approved GPLv2-or-later grant, release acceptance, and explicit approval of the final WordPress.org upload.

## Browser verification on 8 October 2026

All three public URLs loaded successfully in the in-app browser. They still display draft/review labels. That label alone is not a WordPress.org rule requiring another legal-review approval.

The privacy page, last updated 4 October 2026, identifies ReadySpace Network Pte Ltd, 531 Upper Cross Street #03-11, Singapore 050531. Its newsletter section describes email and optional first-name collection, consent and email-code verification, GoHighLevel/LeadConnector signup and delivery, subscriber preferences and unsubscribe handling. An older web-search snapshot omitted these updates; use the current page for publication work.

The privacy text still defers legal-basis details, international-transfer safeguards, concrete retention arrangements and privacy-rights/request handling. It provides no dedicated privacy contact. The terms page, last updated 27 July 2026, still defers cancellation/post-termination terms and warranty, liability and governing-law provisions. The messaging page, last updated 27 July 2026, already states consent, sender-identity and opt-out/suppression obligations; its visible approval label is outdated.

## Concrete publication follow-up

1. Preserve these approved URLs and their existing service/consent wording. Update the shared draft/review status to reflect the recorded owner approval when editing the website source; do not manufacture a legal-review event.
2. Fill the deferred terms/privacy details from the actual service agreement and operating practices. Use the already-published operator identity/address. Obtain the real privacy contact, retention/deletion and transfer arrangements; do not invent them or treat provider names as proof of transfer safeguards.
3. Check the published page against actual newsletter signup, provider tracking and unsubscribe behaviour. The plugin readme separately documents the data sent by this integration and its local ledger/credential retention.

The website source was identified as https://github.com/readyspace/ultimatesales-ai-headless-wp. An isolated local branch, `codex/service-policy-approval`, starts from the latest staging commit `36942c27549151dbde98f25ad500bc7aef48e639`. The prepared `service-policy-owner-approval.patch` in the submission handoff changes only the three pages' approval/status metadata and adds per-page renderer overrides. All five policy bodies and URLs are preserved; acceptable-use and billing/refund pages retain their existing draft status. Terms/privacy notices still identify the unfinished details. Node verified these scope boundaries; the full website build and deployment have not been run for this local patch.

The public policy website is unchanged. Owner approval is recorded; factual completion and actual provider/host acceptance remain open. No WordPress.org upload or newsletter send was made.
