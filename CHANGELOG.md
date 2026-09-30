# Changelog

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
