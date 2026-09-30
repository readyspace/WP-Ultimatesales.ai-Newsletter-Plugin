# Operations and recovery

## Editorial workflow

Write a normal WordPress post and an explicit 10–150-word Excerpt (60–90 recommended). The title becomes the subject after the configured brand prefix. The excerpt becomes the email body. Add the newsletter exclusion checkbox before publication if no email should go out.

Draft saves and edits do not send. Scheduled posts become eligible when WordPress changes them to Published. The first publication waits five minutes. Posts first published while Off remain excluded after enabling. Republishing the same post ID cannot create another queue job. A copied/new post has a new ID and is a different publication.

The public page must return 200 without redirects, match the expected canonical and single H1, and not carry noindex. Headless sites must already map the CMS path to the public path. The plugin does not implement Faust previews or frontend deployment.

## Modes and states

- Off: record first publications as excluded; no archive catch-up.
- Draft-only: validate and create a remote draft; never schedule it.
- Live: validate, create/read back a draft, refresh eligibility, then schedule once.

The usual path is `queued → creating → created → draft_ready` or `queued → creating → created → sending → submitted`.

`submitted` means the API acknowledged the request, not that the mailbox received it. Check provider delivery, bounce, complaint and suppression records.

## Consent

Contacts must match the location and confirmed tag, have valid email and explicit global DND=false, and have no pending/unsubscribed tag or active/unknown email suppression. Emails are deduplicated. The full search is repeated immediately before scheduling. Pagination errors or the 10,000-contact safety boundary stop the whole operation; there is no partial-list send.

GoHighLevel remains authoritative. A contact can unsubscribe after the final read, so provider suppression must also be effective. No contact writes, imports, confirmation, SMS, WhatsApp, tracking installation or consent changes are performed by this plugin.

## Failures

| State | Action |
| --- | --- |
| Held, no campaign ID | Correct the excerpt/public page and use the safe recheck button. |
| Created, creating, sending or uncertain | Inspect the existing remote campaign. Do not recreate it or erase the ledger. |
| Held with campaign ID | Reconcile that draft manually in the provider dashboard. |
| Skipped | No eligible recipients were found; no automatic retry. |
| Submitted, not received | Inspect delivery and suppression; do not resend blindly. |
| Verification pending | Recheck configuration, credential and launch evidence; never force the flag to silence an error. |
| Stale heartbeat | Inspect the scoped host scheduler without logging secrets. |

The per-post database lock and unique ledger key protect local concurrency. Persisting state before writes avoids blind replay after timeouts. This is not a mathematical exactly-once guarantee across a remote API. The alpha has no automated reconciliation or delivery webhook.

## Credential storage

AES-256-GCM encrypts the saved token in a non-autoloaded WordPress option. The key derives from AUTH_KEY and SECURE_AUTH_KEY. This protects a database-only copy, not a compromised server, malicious plugin, administrator or full backup. Changing the keys requires securely re-entering the token. No plugin REST endpoint exposes it. Never store full provider responses in the job note.

## Stop and rollback

Set mode Off, stop only this plugin's host schedule, allow in-flight work to settle, then deactivate. Do not interrupt an uncertain send and immediately repeat it. Keep the ledger, options and remote campaign references. Deactivation cannot recall an email already accepted by the provider. There is no destructive uninstall routine.

Restore compatible plugin files from a private backup if necessary. Check credential format and configuration verification before restarting. Never restore a production database onto an active test site with live sending enabled.
