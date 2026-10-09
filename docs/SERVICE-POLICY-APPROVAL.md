# Service policy approval

ReadySpace's owner confirmed on 8 October 2026 that they read and approve the referenced service policies and their URLs:

- https://ultimatesales.ai/terms
- https://ultimatesales.ai/privacy
- https://ultimatesales.ai/messaging-policy

This completes the owner-review gate for these drafts. It is separate from the previously approved GPLv2-or-later grant, release acceptance, and explicit approval of the final WordPress.org upload.

## Browser verification on 8 October 2026

All three public URLs loaded successfully in the in-app browser. They still display draft/review labels. That label alone is not a WordPress.org rule requiring another legal-review approval.

The privacy page, last updated 4 October 2026, identifies ReadySpace Network Pte Ltd, 531 Upper Cross Street #03-11, Singapore 050531. Its newsletter section describes email and optional first-name collection, consent and email-code verification, GoHighLevel/LeadConnector signup and delivery, subscriber preferences and unsubscribe handling. An older web-search snapshot omitted these updates; use the current page for publication work.

The privacy text still defers legal-basis details, international-transfer safeguards, concrete retention arrangements and privacy-rights/request handling. At inspection it provided no dedicated privacy contact. The owner subsequently confirmed the existing operator/address and specified `contact@readyspace.com` for privacy questions, requests and complaints; those details are included in the isolated website patch. The terms page, last updated 27 July 2026, still defers cancellation/post-termination terms and warranty, liability and governing-law provisions. The messaging page, last updated 27 July 2026, already states consent, sender-identity and opt-out/suppression obligations; its visible approval label is outdated.

## Directory requirements and separate service-policy drafting

WordPress.org guidelines 6/7 require clear external-service/data-use disclosures and authorised connections; policy links are preferred. They do not set a retention period, require a particular rights-request template, or make completion of every SaaS contractual provision a standard submission-form gate. The plugin readme already describes its provider connections, data flow, local ledger/credential retention and tracking limitations. Earlier blanket policy-completion gates are superseded by this distinction.

At the owner's request, the handoff now includes `PRIVACY-NOTICE-PROPOSED.txt`: a concrete proposed service notice using the confirmed company/address/contact, purpose-based retention, limited unsubscribe records, privacy-request handling, international-provider information and separate email/website tracking disclosures. The proposed retention/request/transfer commitments must match actual operations before website publication; no fixed automatic deletion timer or universal legal-compliance guarantee is invented. Actual LC Email Open settings remain unverified, so the notice discloses possible opens where enabled. Google Analytics preferences do not control all HighLevel integrations. Existing policy approvals do not automatically approve these new proposed operating commitments.

The website source is https://github.com/readyspace/ultimatesales-ai-headless-wp. Isolated local branch `codex/service-policy-approval` starts from staging commit `36942c27549151dbde98f25ad500bc7aef48e639`. The prepared `service-policy-owner-approval.patch` changes `content/legal.js` and `components/LegalPage.js`: it records existing terms/messaging approval, adds the contact and proposed privacy wording, and supports per-page labels. Privacy is visibly a proposed update. Other policy bodies/URLs and acceptable-use/billing draft status are preserved. Node verified those scope boundaries; a full website build and deployment have not been run for this local draft.

Next website action: align the new wording with operations, run the normal website build/staging checks, and prepare a PR into `staging` before an explicitly approved publication through the existing workflow. Contract-specific terms remain separate service maintenance.

The public policy website is unchanged. Completed 4 October provider/host acceptance is reused as described in `ACCEPTANCE-BRIDGE.md`; no repeated delivery/unsubscribe check is required for policy drafting alone. WordPress.org subsequently accepted the authorised version `0.3.0` submission and lists Awaiting Review. No newsletter send or policy deployment was performed.

Reference: [WordPress.org detailed guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
